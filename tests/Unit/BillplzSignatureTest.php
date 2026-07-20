<?php

namespace Tests\Unit;

use App\Services\Gateways\BillplzGateway;
use PHPUnit\Framework\TestCase;

class BillplzSignatureTest extends TestCase
{
    private const SECRET = 'test-x-signature-key';

    private function gateway(): BillplzGateway
    {
        return new BillplzGateway([
            'key'           => 'dummy',
            'x_signature'   => self::SECRET,
            'collection_id' => 'abc',
            'sandbox'       => true,
        ]);
    }

    /** Build the signature the way Billplz documents it. */
    private function sign(array $data, string $prefix = ''): string
    {
        $parts = [];
        foreach ($data as $k => $v) {
            $parts[] = $prefix . $k . $v;
        }
        sort($parts, SORT_STRING);

        return hash_hmac('sha256', implode('|', $parts), self::SECRET);
    }

    private function callbackPayload(): array
    {
        return [
            'id'            => 'zq0tm2wc',
            'collection_id' => 'yhx5t1pp',
            'paid'          => 'true',
            'state'         => 'paid',
            'amount'        => '100',
            'paid_amount'   => '100',
            'due_at'        => '2018-9-27',
            'email'         => 'test@billplz.com',
            'mobile'        => '',
            'name'          => 'TESTER',
            'url'           => 'http://www.billplz-sandbox.com/bills/zq0tm2wc',
            'paid_at'       => '2018-09-27 15:15:09 +0800',
        ];
    }

    public function test_source_string_sorts_concatenated_pairs_not_bare_keys(): void
    {
        // Billplz's own example orders paid_amount, then paid_at, then paid — which
        // only happens when the "keyvalue" strings are sorted, not the keys.
        $parts = [];
        foreach ($this->callbackPayload() as $k => $v) {
            $parts[] = $k . $v;
        }
        sort($parts, SORT_STRING);
        $source = implode('|', $parts);

        $this->assertSame(
            'amount100|collection_idyhx5t1pp|due_at2018-9-27|emailtest@billplz.com|idzq0tm2wc|mobile|nameTESTER'
            . '|paid_amount100|paid_at2018-09-27 15:15:09 +0800|paidtrue|statepaid'
            . '|urlhttp://www.billplz-sandbox.com/bills/zq0tm2wc',
            $source
        );
    }

    public function test_it_accepts_a_correctly_signed_callback(): void
    {
        $payload = $this->callbackPayload();
        $payload['x_signature'] = $this->sign($this->callbackPayload());

        $this->assertTrue($this->gateway()->verifySignature($payload));
    }

    public function test_it_rejects_a_tampered_amount(): void
    {
        $payload = $this->callbackPayload();
        $payload['x_signature'] = $this->sign($this->callbackPayload());
        $payload['paid_amount'] = '999999';

        $this->assertFalse($this->gateway()->verifySignature($payload));
    }

    public function test_it_rejects_a_forged_or_missing_signature(): void
    {
        $payload = $this->callbackPayload();

        $this->assertFalse($this->gateway()->verifySignature($payload), 'missing signature must fail');

        $payload['x_signature'] = str_repeat('a', 64);
        $this->assertFalse($this->gateway()->verifySignature($payload), 'forged signature must fail');
    }

    public function test_it_verifies_the_redirect_payload_with_the_billplz_prefix(): void
    {
        $inner = [
            'id'      => 'zq0tm2wc',
            'paid'    => 'true',
            'paid_at' => '2018-09-27 15:15:09 +0800',
        ];
        $payload = ['billplz' => $inner + ['x_signature' => $this->sign($inner, 'billplz')]];

        $this->assertTrue($this->gateway()->verifySignature($payload, true));
        $this->assertSame('zq0tm2wc', $this->gateway()->referenceFrom($payload, true));

        $payload['billplz']['paid'] = 'false';
        $this->assertFalse($this->gateway()->verifySignature($payload, true));
    }

    public function test_it_refuses_to_verify_when_no_secret_is_configured(): void
    {
        $gateway = new BillplzGateway(['key' => 'dummy', 'x_signature' => null]);
        $payload = $this->callbackPayload();
        $payload['x_signature'] = $this->sign($this->callbackPayload());

        $this->assertFalse($gateway->verifySignature($payload));
    }
}
