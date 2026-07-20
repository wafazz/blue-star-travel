<?php

namespace App\Services\Gateways;

use App\Models\Payment;

/**
 * Local simulator. It has no vendor behind it, so it can never prove money moved —
 * a "successful" sandbox payment is only ever recorded as pending for staff to
 * verify, and its screens 404 in production.
 */
class SandboxGateway implements PaymentGatewayDriver
{
    // FPX-style bank list for the simulated checkout screen.
    const BANKS = [
        'MB2U0227' => 'Maybank2u',
        'CIMB0032' => 'CIMB Clicks',
        'PBB0233'  => 'Public Bank',
        'RHB0218'  => 'RHB Now',
        'BIMB0340' => 'Bank Islam',
        'HLB0224'  => 'Hong Leong Connect',
        'AMBB0209' => 'AmBank',
        'BKRM0602' => 'Bank Rakyat',
    ];

    public function __construct(private array $config = []) {}

    public function key(): string
    {
        return 'sandbox';
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'FPX Sandbox (simulated)';
    }

    public function start(Payment $payment, string $callbackUrl, string $returnUrl): string
    {
        return route('gateway.checkout', $payment->gateway_ref);
    }

    /** Nothing is signed — the simulator is never trusted to credit a booking. */
    public function verifySignature(array $payload, bool $isRedirect = false): bool
    {
        return false;
    }

    public function referenceFrom(array $payload, bool $isRedirect = false): ?string
    {
        return $payload['ref'] ?? null;
    }

    public function fetchStatus(Payment $payment): ?array
    {
        return null;
    }
}
