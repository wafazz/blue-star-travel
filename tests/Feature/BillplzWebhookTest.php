<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\Payment;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillplzWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-x-signature-key';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payments.default', 'billplz');
        config()->set('payments.drivers.billplz.key', 'test-api-key');
        config()->set('payments.drivers.billplz.x_signature', self::SECRET);
        config()->set('payments.drivers.billplz.collection_id', 'testcol');
        config()->set('payments.drivers.billplz.sandbox', true);
    }

    private function makePayment(float $amount = 250.00): Payment
    {
        $provider = Provider::create(['name' => 'Test Operator', 'type' => 'local_operator', 'status' => 'active']);
        $package  = Package::create([
            'code' => 'PKG1', 'title' => 'Test Trip', 'slug' => 'test-trip', 'category' => 'domestic',
            'provider_id' => $provider->id, 'destination' => 'Langkawi', 'duration_days' => 3,
            'duration_nights' => 2, 'status' => 'active',
        ]);
        PackagePricing::create([
            'package_id' => $package->id, 'tier_name' => 'Standard', 'adult_price' => $amount,
            'child_price' => 0, 'infant_price' => 0, 'is_default' => true,
        ]);
        $customer = Customer::create(['name' => 'Test Customer', 'email' => 'c@test.com', 'phone' => '0123', 'status' => 'active']);

        $booking = Booking::create([
            'booking_no' => 'BK-TEST-0001', 'package_id' => $package->id, 'customer_id' => $customer->id,
            'provider_id' => $provider->id, 'type' => 'online', 'status' => 'pending_verification',
            'adults' => 1, 'children' => 0, 'infants' => 0, 'total_pax' => 1,
            'adult_price' => $amount, 'child_price' => 0, 'infant_price' => 0,
            'subtotal' => $amount, 'discount' => 0, 'total_amount' => $amount, 'paid_amount' => 0,
        ]);

        return $booking->payments()->create([
            'amount' => $amount, 'method' => 'fpx', 'gateway' => 'billplz',
            'gateway_ref' => 'bill123', 'type' => 'full', 'status' => 'pending',
        ]);
    }

    private function signedPayload(array $overrides = []): array
    {
        $data = array_merge([
            'id'            => 'bill123',
            'collection_id' => 'testcol',
            'paid'          => 'true',
            'state'         => 'paid',
            'amount'        => '25000',
            'paid_amount'   => '25000',
            'email'         => 'c@test.com',
            'name'          => 'Test Customer',
        ], $overrides);

        $parts = [];
        foreach ($data as $k => $v) {
            $parts[] = $k . $v;
        }
        sort($parts, SORT_STRING);
        $data['x_signature'] = hash_hmac('sha256', implode('|', $parts), self::SECRET);

        return $data;
    }

    private function fakeBill(bool $paid = true, int $paidAmountCents = 25000): void
    {
        Http::fake(['*/api/v3/bills/*' => Http::response([
            'id' => 'bill123', 'paid' => $paid, 'state' => $paid ? 'paid' : 'due',
            'amount' => 25000, 'paid_amount' => $paidAmountCents,
        ], 200)]);
    }

    public function test_a_signed_paid_webhook_credits_the_booking(): void
    {
        $payment = $this->makePayment();
        $this->fakeBill();

        $this->post('/pay/webhook/billplz', $this->signedPayload())->assertOk();

        $payment->refresh();
        $this->assertSame('verified', $payment->status);
        $this->assertEquals(250.00, (float) $payment->booking->fresh()->paid_amount);
    }

    public function test_a_forged_signature_is_rejected_and_credits_nothing(): void
    {
        $payment = $this->makePayment();
        $this->fakeBill();

        $payload = $this->signedPayload();
        $payload['x_signature'] = str_repeat('f', 64);

        $this->post('/pay/webhook/billplz', $payload)->assertForbidden();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertEquals(0.0, (float) $payment->booking->fresh()->paid_amount);
    }

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        $payment = $this->makePayment();
        $this->fakeBill();

        $payload = $this->signedPayload();
        unset($payload['x_signature']);

        $this->post('/pay/webhook/billplz', $payload)->assertForbidden();
        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_the_vendor_not_the_payload_decides_whether_money_arrived(): void
    {
        // Payload claims paid, but the vendor lookup says it is still due.
        $payment = $this->makePayment();
        $this->fakeBill(paid: false);

        $this->post('/pay/webhook/billplz', $this->signedPayload())->assertOk();

        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertEquals(0.0, (float) $payment->booking->fresh()->paid_amount);
    }

    public function test_a_short_payment_is_held_for_review_not_credited(): void
    {
        $payment = $this->makePayment();
        $this->fakeBill(paid: true, paidAmountCents: 10000); // RM100 against RM250

        $this->post('/pay/webhook/billplz', $this->signedPayload())->assertOk();

        $payment->refresh();
        $this->assertNotSame('verified', $payment->status);
        $this->assertEquals(0.0, (float) $payment->booking->fresh()->paid_amount);
    }

    public function test_repeat_webhooks_are_idempotent(): void
    {
        $payment = $this->makePayment();
        $this->fakeBill();

        $this->post('/pay/webhook/billplz', $this->signedPayload())->assertOk();
        $this->post('/pay/webhook/billplz', $this->signedPayload())->assertOk();

        $this->assertEquals(250.00, (float) $payment->booking->fresh()->paid_amount);
        $this->assertSame(1, $payment->booking->payments()->where('status', 'verified')->count());
    }

    public function test_an_unknown_reference_is_a_404(): void
    {
        $this->fakeBill();

        $this->post('/pay/webhook/billplz', $this->signedPayload(['id' => 'nope']))->assertNotFound();
    }
}
