<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gateways\PaymentGatewayDriver;
use App\Services\Gateways\SandboxGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    // Kept for the sandbox checkout view.
    const BANKS = SandboxGateway::BANKS;

    public function __construct(
        private BookingService $bookings,
        private PaymentGatewayManager $gateways,
    ) {}

    public function driver(): PaymentGatewayDriver
    {
        return $this->gateways->default();
    }

    /** Create the pending payment row the gateway will be asked to collect. */
    public function initiate(Booking $booking, float $amount, ?User $actor): Payment
    {
        return DB::transaction(function () use ($booking, $amount, $actor) {
            $driver = $this->driver();

            return $booking->payments()->create([
                'recorded_by'  => $actor?->id,
                'amount'       => round($amount, 2),
                'method'       => 'fpx',
                'gateway'      => $driver->key(),
                'gateway_ref'  => 'PAY' . now()->format('ymdHis') . strtoupper(Str::random(4)),
                'type'         => $booking->paid_amount > 0 ? 'balance' : 'full',
                'status'       => 'pending',
                'note'         => 'Awaiting ' . $driver->label(),
            ]);
        });
    }

    /**
     * Settle a payment against what the VENDOR says, never against anything the
     * payer's browser submitted. Only a server-confirmed, correctly-signed, fully
     * paid bill verifies the payment (which is what credits the booking and fires
     * the commission cascade). Idempotent — repeat callbacks are a no-op.
     */
    public function settle(Payment $payment, array $status, ?User $actor = null): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment; // already resolved
        }

        $payment->update([
            'gateway_payload' => $status['raw'] ?? [],
            'reference'       => $status['reference'] ?? $payment->gateway_ref,
            'paid_at'         => now(),
        ]);

        $paidEnough = ((float) ($status['amount'] ?? 0)) + 0.001 >= (float) $payment->amount;

        if (! empty($status['paid']) && $paidEnough) {
            $this->bookings->verifyPayment($payment->fresh(), $actor);
        } elseif (! empty($status['paid'])) {
            // Signed and paid, but short — never auto-credit a mismatched amount.
            $this->bookings->log(
                $payment->booking,
                $actor,
                'Gateway reported RM ' . number_format((float) $status['amount'], 2)
                    . ' against RM ' . number_format((float) $payment->amount, 2) . ' — held for manual review',
                $payment->gateway_ref,
            );
        } else {
            $this->bookings->rejectPayment($payment, $actor);
        }

        return $payment->fresh();
    }

    /** Re-query the vendor for this payment and settle on the answer. */
    public function sync(Payment $payment, ?User $actor = null): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        $status = $this->gateways->driver($payment->gateway ?: $this->gateways->defaultKey())->fetchStatus($payment);

        return $status ? $this->settle($payment, $status, $actor) : $payment;
    }

    /**
     * The SANDBOX result is declared by the payer's own browser, so it proves
     * nothing — a simulated success only records the authorisation and leaves the
     * payment pending staff verification, exactly like a bank slip.
     */
    public function handleSandboxCallback(string $ref, bool $success, string $bank = '', ?User $actor = null): ?Payment
    {
        $payment = Payment::where('gateway_ref', $ref)->first();
        if (! $payment || $payment->status !== 'pending') {
            return $payment;
        }

        $payment->update([
            'gateway_payload' => [
                'bank'         => $bank,
                'result'       => $success ? 'approved' : 'declined',
                'processed_at' => now()->toIso8601String(),
            ],
            'reference' => $ref,
            'paid_at'   => now(),
        ]);

        if ($success) {
            $this->bookings->log(
                $payment->booking,
                $actor,
                'FPX authorisation received (RM ' . number_format((float) $payment->amount, 2) . ') — pending verification',
                $bank ?: null,
            );
        } else {
            $this->bookings->rejectPayment($payment, $actor);
        }

        return $payment->fresh();
    }
}
