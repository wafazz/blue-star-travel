<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\CommissionLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic multi-layer commission engine.
 *  - Cascade DEPTH = number of active commission_levels rows (admin-configurable).
 *  - Level N pays the agent N steps up the seller's upline (closure table).
 *  - Missing upline at a level → orphan, reserved to HQ (earner_id null).
 *  - Runs land `pending`; HQ approval credits the agent wallet (KPDN safeguard).
 *  - Idempotent per booking; reversible on refund/cancel.
 */
class CommissionService
{
    public function __construct(
        private AgentTreeService $tree,
        private WalletService $wallet,
        private NotificationService $notifications,
    ) {}

    public function calculate(Booking $booking): void
    {
        $sellerId = $booking->agent_id;
        if (! $sellerId) {
            return; // house / direct booking — no agent to pay
        }

        // idempotent — never double-book a booking's commissions
        if (Commission::where('booking_id', $booking->id)->where('status', '!=', 'reversed')->exists()) {
            return;
        }

        $levels = CommissionLevel::activeOrdered();
        if ($levels->isEmpty()) {
            return;
        }

        $base   = (float) $booking->total_amount;
        $period = now()->format('Y-m');
        $upline = $this->tree->uplineChain($sellerId, $levels->max('level'))
            ->keyBy('depth'); // depth => row(user_id)

        DB::transaction(function () use ($booking, $levels, $upline, $base, $period, $sellerId) {
            foreach ($levels as $lvl) {
                $earnerId = optional($upline->get($lvl->level))->user_id;
                $amount = round($base * (float) $lvl->percent / 100, 2);

                Commission::create([
                    'booking_id'      => $booking->id,
                    'earner_id'       => $earnerId,               // null → orphan/HQ
                    'source_agent_id' => $sellerId,
                    'level'           => $lvl->level,
                    'is_orphan'       => $earnerId === null,
                    'base_amount'     => $base,
                    'percent'         => $lvl->percent,
                    'amount'          => $amount,
                    'status'          => 'pending',
                    'period'          => $period,
                ]);
            }
        });
    }

    public function approve(Commission $commission, ?User $actor): void
    {
        if ($commission->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($commission) {
            $commission->update(['status' => 'approved', 'approved_at' => now()]);

            if ($commission->earner_id && ! $commission->is_orphan) {
                $this->wallet->credit(
                    $commission->earner,
                    (float) $commission->amount,
                    "Commission L{$commission->level} · booking {$commission->booking->booking_no}",
                    $commission,
                    "COMM-{$commission->id}"
                );
                $this->notifications->notify(
                    $commission->earner, 'commission',
                    'Commission approved: RM ' . number_format((float) $commission->amount, 2),
                    "L{$commission->level} on booking {$commission->booking->booking_no} — credited to your wallet.",
                    route('agent.wallet.index'),
                );
            }
        });
    }

    public function reject(Commission $commission, ?User $actor): void
    {
        if ($commission->status !== 'pending') {
            return;
        }
        $commission->update(['status' => 'reversed', 'note' => 'Rejected by ' . ($actor?->name ?? 'system')]);
    }

    public function approvePeriod(string $period, ?User $actor): int
    {
        $pending = Commission::where('period', $period)->where('status', 'pending')->get();
        foreach ($pending as $c) {
            $this->approve($c, $actor);
        }

        return $pending->count();
    }

    /** Reverse all live commissions for a booking (on refund/cancel). Claws back wallet credits. */
    public function reverse(Booking $booking, ?User $actor): void
    {
        $commissions = Commission::where('booking_id', $booking->id)
            ->whereIn('status', ['pending', 'approved', 'paid'])->get();

        DB::transaction(function () use ($commissions) {
            foreach ($commissions as $c) {
                if (in_array($c->status, ['approved', 'paid'], true) && $c->earner_id && ! $c->is_orphan) {
                    $this->wallet->debit(
                        $c->earner,
                        (float) $c->amount,
                        "Reversal · commission L{$c->level} · booking {$c->booking->booking_no}",
                        $c,
                        "REV-{$c->id}"
                    );
                }
                $c->update(['status' => 'reversed', 'note' => 'Reversed (booking refunded/cancelled)']);
            }
        });
    }
}
