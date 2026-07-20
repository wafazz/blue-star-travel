<?php

namespace App\Services\Gateways;

use App\Models\Payment;

interface PaymentGatewayDriver
{
    /** Config key this driver is registered under (billplz, sandbox, …). */
    public function key(): string;

    /** Human label shown on checkout buttons. */
    public function label(): string;

    /**
     * Start a payment. Returns the URL the payer should be sent to, and may write
     * the vendor's own reference onto $payment (gateway_ref).
     */
    public function start(Payment $payment, string $callbackUrl, string $returnUrl): string;

    /**
     * Verify a signed vendor message (webhook payload or redirect query).
     * MUST return false when the signature is absent or does not match — this is
     * the only thing standing between the app and a forged "payment received".
     */
    public function verifySignature(array $payload, bool $isRedirect = false): bool;

    /**
     * Ask the vendor, server-to-server, what actually happened to this payment.
     * Returns ['paid' => bool, 'amount' => float, 'reference' => string, 'raw' => array]
     * or null when the payment cannot be resolved.
     */
    public function fetchStatus(Payment $payment): ?array;

    /** Pull the vendor's payment reference out of a webhook/redirect payload. */
    public function referenceFrom(array $payload, bool $isRedirect = false): ?string;
}
