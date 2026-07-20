<?php

namespace App\Services\Gateways;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BillplzGateway implements PaymentGatewayDriver
{
    public function __construct(private array $config) {}

    public function key(): string
    {
        return 'billplz';
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'FPX / Online Banking (Billplz)';
    }

    private function baseUrl(): string
    {
        return ($this->config['sandbox'] ?? true)
            ? 'https://www.billplz-sandbox.com/api/v3'
            : 'https://www.billplz.com/api/v3';
    }

    private function request()
    {
        $key = $this->config['key'] ?? null;
        if (! $key) {
            throw new RuntimeException('BILLPLZ_KEY is not configured.');
        }

        // Billplz uses HTTP Basic with the API key as the username and no password.
        return Http::withBasicAuth($key, '')->asForm()->timeout(20);
    }

    public function start(Payment $payment, string $callbackUrl, string $returnUrl): string
    {
        $booking  = $payment->booking;
        $customer = $booking->customer;

        $response = $this->request()->post($this->baseUrl() . '/bills', [
            'collection_id' => $this->config['collection_id'],
            'email'         => $customer?->email ?: 'noreply@bluetravel.com',
            'mobile'        => $customer?->phone,
            'name'          => $customer?->name ?: 'Customer',
            // Billplz takes the smallest currency unit — RM 1.00 is 100.
            'amount'        => (int) round((float) $payment->amount * 100),
            'callback_url'  => $callbackUrl,
            'redirect_url'  => $returnUrl,
            'description'   => mb_substr('Booking ' . $booking->booking_no . ' — ' . ($booking->package?->title ?? 'Travel package'), 0, 200),
            'reference_1_label' => 'Booking',
            'reference_1'   => $booking->booking_no,
        ]);

        if (! $response->successful()) {
            Log::error('Billplz bill creation failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Could not start the payment. Please try again or use bank transfer.');
        }

        $bill = $response->json();

        $payment->update([
            'gateway'         => 'billplz',
            'gateway_ref'     => $bill['id'],
            'gateway_payload' => $bill,
        ]);

        return $bill['url'];
    }

    /**
     * Billplz signs every callback and redirect with HMAC-SHA256 over a source
     * string built from the payload itself. Build "key.value" for each field
     * (excluding the signature), sort those CONCATENATED strings ascending, and
     * join them with "|" — sorting the joined strings (not the bare keys) is what
     * puts `paid_amount…` before `paid…`, matching Billplz's own examples.
     */
    public function verifySignature(array $payload, bool $isRedirect = false): bool
    {
        $secret = $this->config['x_signature'] ?? null;
        if (! $secret) {
            return false;
        }

        $data = $isRedirect ? ($payload['billplz'] ?? []) : $payload;
        if (! is_array($data) || $data === []) {
            return false;
        }

        $given = $data['x_signature'] ?? null;
        if (! is_string($given) || $given === '') {
            return false;
        }
        unset($data['x_signature']);

        $prefix = $isRedirect ? 'billplz' : '';
        $parts = [];
        foreach ($data as $field => $value) {
            if (is_array($value)) {
                continue;
            }
            $parts[] = $prefix . $field . $value;
        }
        sort($parts, SORT_STRING);

        $expected = hash_hmac('sha256', implode('|', $parts), $secret);

        return hash_equals($expected, $given);
    }

    public function referenceFrom(array $payload, bool $isRedirect = false): ?string
    {
        $data = $isRedirect ? ($payload['billplz'] ?? []) : $payload;

        return is_array($data) && ! empty($data['id']) ? (string) $data['id'] : null;
    }

    public function fetchStatus(Payment $payment): ?array
    {
        if (! $payment->gateway_ref) {
            return null;
        }

        $response = Http::withBasicAuth($this->config['key'] ?? '', '')
            ->timeout(20)->get($this->baseUrl() . '/bills/' . $payment->gateway_ref);

        if (! $response->successful()) {
            Log::warning('Billplz bill lookup failed', ['ref' => $payment->gateway_ref, 'status' => $response->status()]);

            return null;
        }

        $bill = $response->json();

        return [
            'paid'      => (bool) ($bill['paid'] ?? false),
            'amount'    => ((int) ($bill['paid_amount'] ?? $bill['amount'] ?? 0)) / 100,
            'reference' => (string) ($bill['id'] ?? $payment->gateway_ref),
            'raw'       => $bill,
        ];
    }
}
