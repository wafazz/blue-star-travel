<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaymentGatewayController extends Controller
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function edit()
    {
        return view('manage.settings.payment-gateway', [
            'activeDriver' => $this->gateways->defaultKey(),
            'drivers'      => $this->gateways->available(),
            'billplz'      => [
                'key'           => $this->gateways->mask($this->gateways->setting('billplz', 'key') ?: config('payments.drivers.billplz.key')),
                'x_signature'   => $this->gateways->mask($this->gateways->setting('billplz', 'x_signature') ?: config('payments.drivers.billplz.x_signature')),
                'collection_id' => $this->gateways->configFor('billplz')['collection_id'] ?? null,
                'sandbox'       => (bool) ($this->gateways->configFor('billplz')['sandbox'] ?? true),
            ],
            'fromEnv'      => [
                'key'         => ! $this->gateways->setting('billplz', 'key') && config('payments.drivers.billplz.key'),
                'x_signature' => ! $this->gateways->setting('billplz', 'x_signature') && config('payments.drivers.billplz.x_signature'),
            ],
            'webhookUrl'   => route('gateway.webhook', 'billplz'),
            'returnUrl'    => route('gateway.return', 'billplz'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'driver'                => ['required', 'in:' . implode(',', array_keys($this->gateways->available()))],
            'billplz_key'           => ['nullable', 'string', 'max:255'],
            'billplz_x_signature'   => ['nullable', 'string', 'max:255'],
            'billplz_collection_id' => ['nullable', 'string', 'max:100'],
            'billplz_sandbox'       => ['nullable', 'boolean'],
        ]);

        // Going live needs credentials — refuse to switch on a half-configured gateway.
        if ($data['driver'] === 'billplz') {
            $stored = $this->gateways->configFor('billplz');
            $value = fn (string $field) => ($data['billplz_' . $field] ?? null) ?: ($stored[$field] ?? null);

            if (! $value('key') || ! $value('x_signature') || ! $value('collection_id')) {
                return back()->withErrors([
                    'driver' => 'Billplz needs an API key, X-Signature key and collection ID before it can be activated.',
                ])->withInput();
            }
        }

        Setting::put('payment.driver', $data['driver']);

        // Blank secret = keep the stored one (the form only ever shows a mask).
        foreach (['key', 'x_signature'] as $field) {
            if (filled($data['billplz_' . $field] ?? null)) {
                $this->gateways->saveSetting('billplz', $field, $data['billplz_' . $field]);
            }
        }
        $this->gateways->saveSetting('billplz', 'collection_id', $data['billplz_collection_id'] ?? null);
        $this->gateways->saveSetting('billplz', 'sandbox', $request->boolean('billplz_sandbox') ? '1' : '0');

        return redirect()->route('manage.payment-gateway.edit')
            ->with('ok', 'Payment gateway settings saved. Active gateway: ' . ($this->gateways->available()[$data['driver']] ?? $data['driver']) . '.');
    }

    /** Ask Billplz whether these credentials actually work, without taking a payment. */
    public function test()
    {
        $config = $this->gateways->configFor('billplz');

        if (empty($config['key']) || empty($config['collection_id'])) {
            return back()->withErrors(['driver' => 'Set the API key and collection ID first.']);
        }

        $base = ($config['sandbox'] ?? true)
            ? 'https://www.billplz-sandbox.com/api/v3'
            : 'https://www.billplz.com/api/v3';

        try {
            $response = Http::withBasicAuth($config['key'], '')->timeout(15)
                ->get($base . '/collections/' . $config['collection_id']);
        } catch (Throwable $e) {
            return back()->withErrors(['driver' => 'Could not reach Billplz: ' . $e->getMessage()]);
        }

        if ($response->status() === 401) {
            return back()->withErrors(['driver' => 'Billplz rejected the API key (401). Check the key and whether you are on sandbox or live.']);
        }
        if ($response->status() === 404) {
            return back()->withErrors(['driver' => 'Billplz could not find that collection ID (404).']);
        }
        if (! $response->successful()) {
            return back()->withErrors(['driver' => 'Billplz returned HTTP ' . $response->status() . '.']);
        }

        $collection = $response->json();

        return back()->with('ok', 'Connected to Billplz ✅ Collection "' . ($collection['title'] ?? $config['collection_id'])
            . '" (' . (($config['sandbox'] ?? true) ? 'sandbox' : 'live') . ').');
    }
}
