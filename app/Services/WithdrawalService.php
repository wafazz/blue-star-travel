<?php

namespace App\Services;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(
        private WalletService $wallet,
        private NotificationService $notifications,
    ) {}

    public function generateNo(): string
    {
        $prefix = 'WD-' . now()->format('Y') . '-';
        $last = Withdrawal::where('withdrawal_no', 'like', $prefix . '%')->orderByDesc('id')->value('withdrawal_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** Agent requests a withdrawal — funds are held (debited) immediately. */
    public function request(User $user, array $data): Withdrawal
    {
        return DB::transaction(function () use ($user, $data) {
            $amount = round((float) $data['amount'], 2);
            $wallet = $this->wallet->walletFor($user);

            if ($amount > (float) $wallet->balance) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds available wallet balance.']);
            }

            $withdrawal = Withdrawal::create([
                'withdrawal_no'     => $this->generateNo(),
                'user_id'           => $user->id,
                'amount'            => $amount,
                'bank_name'         => $data['bank_name'] ?? null,
                'bank_account_no'   => $data['bank_account_no'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null,
                'status'            => 'pending',
                'note'              => $data['note'] ?? null,
            ]);

            // hold the funds
            $this->wallet->debit($user, $amount, "Withdrawal hold · {$withdrawal->withdrawal_no}", $withdrawal, $withdrawal->withdrawal_no);

            return $withdrawal;
        });
    }

    public function approve(Withdrawal $withdrawal, ?User $actor, ?string $note = null): void
    {
        $withdrawal->update(['status' => 'approved', 'approved_by' => $actor?->id, 'admin_note' => $note]);
    }

    public function markPaid(Withdrawal $withdrawal, ?User $actor): void
    {
        DB::transaction(function () use ($withdrawal, $actor) {
            $withdrawal->update(['status' => 'paid', 'approved_by' => $withdrawal->approved_by ?? $actor?->id, 'paid_at' => now()]);
            $this->wallet->markWithdrawn($withdrawal->user, (float) $withdrawal->amount);
            $this->notifications->notify(
                $withdrawal->user, 'withdrawal',
                'Withdrawal paid: RM ' . number_format((float) $withdrawal->amount, 2),
                "{$withdrawal->withdrawal_no} has been paid out.",
                route('agent.wallet.index'),
            );
        });
    }

    /** Reject a pending/approved withdrawal — funds returned to wallet. */
    public function reject(Withdrawal $withdrawal, ?User $actor, ?string $note = null): void
    {
        if ($withdrawal->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($withdrawal, $actor, $note) {
            $this->wallet->credit($withdrawal->user, (float) $withdrawal->amount, "Withdrawal refund · {$withdrawal->withdrawal_no}", $withdrawal, $withdrawal->withdrawal_no, false);
            $withdrawal->update(['status' => 'rejected', 'approved_by' => $actor?->id, 'admin_note' => $note]);
        });
    }
}
