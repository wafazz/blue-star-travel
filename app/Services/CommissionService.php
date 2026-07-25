<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Commission;
use App\Models\CommissionLevel;
use App\Models\Setting;
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

        // Per-package commission (dynamic depth, fixed/percent toggle, per pax-type) takes
        // precedence; a package with no agent rows falls back to the global default levels.
        $pkgLevels   = optional($booking->package)->activeCommissionLevels() ?? collect();
        $agentLevels = $pkgLevels->where('is_hq', false)->values();
        $rows = $agentLevels->isNotEmpty()
            ? $this->packageRows($booking, $agentLevels)
            : $this->globalRows($booking);

        // Company / HQ override line — always earned by HQ on top of the agent cascade.
        // Package-specific HQ level wins; otherwise the global default HQ commission applies.
        $hqRow = $this->hqRow($booking, $pkgLevels->firstWhere('is_hq', true));

        if (empty($rows) && ! $hqRow) {
            return;
        }

        $period   = now()->format('Y-m');
        $maxLevel = empty($rows) ? 0 : max(array_column($rows, 'level'));
        $upline   = $this->tree->uplineChain($sellerId, $maxLevel)->keyBy('depth');

        DB::transaction(function () use ($booking, $rows, $hqRow, $upline, $period, $sellerId) {
            foreach ($rows as $row) {
                $earnerId = optional($upline->get($row['level']))->user_id;

                Commission::create([
                    'booking_id'      => $booking->id,
                    'earner_id'       => $earnerId,               // null → orphan/HQ
                    'source_agent_id' => $sellerId,
                    'level'           => $row['level'],
                    'is_orphan'       => $earnerId === null,
                    'rate_type'       => $row['rate_type'],
                    'base_amount'     => $row['base'],
                    'percent'         => $row['percent'],
                    'amount'          => $row['amount'],
                    'status'          => 'pending',
                    'period'          => $period,
                ]);
            }

            if ($hqRow) {
                Commission::create([
                    'booking_id'      => $booking->id,
                    'earner_id'       => null,                    // company account (no agent wallet)
                    'source_agent_id' => $sellerId,
                    'level'           => 0,
                    'is_orphan'       => false,
                    'is_hq'           => true,
                    'rate_type'       => $hqRow['rate_type'],
                    'base_amount'     => $hqRow['base'],
                    'percent'         => $hqRow['percent'],
                    'amount'          => $hqRow['amount'],
                    'status'          => 'pending',
                    'period'          => $period,
                ]);
            }
        });
    }

    /** Compute one commission line from a rate_type + a per-pax value resolver. */
    private function computeLine(string $rateType, callable $valueFor, array $pax): array
    {
        $amount = 0.0;
        $base   = 0.0;
        foreach ($pax as $type => $p) {
            if ($p['count'] <= 0) {
                continue;
            }
            $value = (float) $valueFor($type);
            if ($rateType === 'fixed') {
                $amount += $p['count'] * $value;                 // flat RM per pax
            } else {
                $lineBase = $p['count'] * $p['price'];
                $base     += $lineBase;
                $amount   += $lineBase * $value / 100;           // % of that pax's fare
            }
        }
        $amount = round($amount, 2);
        if ($rateType === 'fixed') {
            $base = $amount; // no percentage base for a flat payout
        }

        return [
            'rate_type' => $rateType,
            'base'      => round($base, 2),
            'percent'   => $rateType === 'percent' && $base > 0 ? round($amount / $base * 100, 2) : 0,
            'amount'    => $amount,
        ];
    }

    /** Resolve + compute the HQ override line (package level, else global default). Null when disabled/zero. */
    private function hqRow(Booking $booking, $pkgHqLevel): ?array
    {
        if ($pkgHqLevel) {
            $rateType = $pkgHqLevel->rate_type;
            $valueFor = fn ($type) => $pkgHqLevel->valueFor($type);
        } else {
            $cfg = $this->hqDefault();
            if (! $cfg || empty($cfg['active'])) {
                return null;
            }
            $rateType = ($cfg['rate_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
            $valueFor = fn ($type) => (float) ($cfg[$type . '_value'] ?? 0);
        }

        $line = $this->computeLine($rateType, $valueFor, $this->paxBreakdown($booking));

        return $line['amount'] > 0 ? $line : null;
    }

    /** Global default HQ commission config from settings, or null. */
    public function hqDefault(): ?array
    {
        $raw = Setting::get('hq_commission');
        if (! $raw) {
            return null;
        }

        return is_array($raw) ? $raw : (json_decode($raw, true) ?: null);
    }

    /**
     * Pax-type breakdown for a booking → [type => ['count' => n, 'price' => rm]].
     *
     * A booking can span several room types at different per-pax rates, so `price` is the
     * fare-weighted average: count × price reproduces the exact total fare for that type,
     * which is what a percentage commission is charged on. Bookings with no room lines
     * (customer portal, seeders, pre-migration rows) read the legacy single-rate columns.
     */
    private function paxBreakdown(Booking $booking): array
    {
        $rooms = $booking->relationLoaded('rooms') ? $booking->rooms : $booking->rooms()->get();

        if ($rooms->isEmpty()) {
            return [
                'adult'  => ['count' => (int) $booking->adults,   'price' => (float) $booking->adult_price],
                'child'  => ['count' => (int) $booking->children, 'price' => (float) $booking->child_price],
                'senior' => ['count' => (int) $booking->seniors,  'price' => (float) $booking->senior_price],
                'infant' => ['count' => (int) $booking->infants,  'price' => (float) $booking->infant_price],
            ];
        }

        $out = [];
        foreach (['adult' => 'adults', 'child' => 'children', 'senior' => 'seniors', 'infant' => 'infants'] as $type => $column) {
            $count = 0;
            $fare  = 0.0;
            foreach ($rooms as $room) {
                $n = (int) $room->{$column};
                $count += $n;
                $fare  += $n * (float) $room->{$type . '_price'};
            }
            $out[$type] = ['count' => $count, 'price' => $count > 0 ? $fare / $count : 0.0];
        }

        return $out;
    }

    /** Build per-level commission rows from the package's own configuration. */
    private function packageRows(Booking $booking, $pkgLevels): array
    {
        $pax  = $this->paxBreakdown($booking);
        $rows = [];

        foreach ($pkgLevels as $lvl) {
            $rows[] = ['level' => (int) $lvl->level]
                + $this->computeLine($lvl->rate_type, fn ($type) => $lvl->valueFor($type), $pax);
        }

        return $rows;
    }

    /** Legacy fallback — flat percent of the booking total, from the global commission_levels table. */
    private function globalRows(Booking $booking): array
    {
        $levels = CommissionLevel::activeOrdered();
        if ($levels->isEmpty()) {
            return [];
        }

        $base = (float) $booking->total_amount;

        return $levels->map(fn ($lvl) => [
            'level'     => (int) $lvl->level,
            'rate_type' => 'percent',
            'base'      => $base,
            'percent'   => (float) $lvl->percent,
            'amount'    => round($base * (float) $lvl->percent / 100, 2),
        ])->all();
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
