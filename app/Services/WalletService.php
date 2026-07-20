<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    public function credit(User $user, float $amount, string $description, ?Model $source = null, ?string $reference = null, bool $countAsEarning = true): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $source, $reference, $countAsEarning) {
            $wallet = $this->walletFor($user);
            $wallet->increment('balance', $amount);
            if ($countAsEarning) {
                $wallet->increment('total_earned', $amount);
            }
            $wallet->refresh();

            return $this->log($wallet, 'credit', $amount, $description, $source, $reference);
        });
    }

    public function debit(User $user, float $amount, string $description, ?Model $source = null, ?string $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $source, $reference) {
            $wallet = $this->walletFor($user);
            $wallet->decrement('balance', $amount);
            $wallet->refresh();

            return $this->log($wallet, 'debit', $amount, $description, $source, $reference);
        });
    }

    public function markWithdrawn(User $user, float $amount): void
    {
        $wallet = $this->walletFor($user);
        $wallet->increment('total_withdrawn', $amount);
    }

    private function log(Wallet $wallet, string $type, float $amount, string $description, ?Model $source, ?string $reference): WalletTransaction
    {
        return $wallet->transactions()->create([
            'type'          => $type,
            'amount'        => round($amount, 2),
            'balance_after' => $wallet->balance,
            'reference'     => $reference,
            'description'   => $description,
            'source_type'   => $source ? $source->getMorphClass() : null,
            'source_id'     => $source?->getKey(),
        ]);
    }
}
