<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\Assistant\AiProviderException;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\ProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HQ/super-admin screen for the AI assistant (OQ-1, OQ-2, OQ-4). Mirrors the payment gateway screen:
 * secrets are write-only, and every change is written to the log without the key.
 */
class AiAssistantController extends Controller
{
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly ProviderFactory $factory,
    ) {}

    public function edit()
    {
        $providers = [];
        foreach (AssistantSettings::PROVIDERS as $key) {
            $providers[$key] = [
                'label' => config("ai.drivers.{$key}.label"),
                'model' => $this->settings->model($key),
                'model_default' => config("ai.drivers.{$key}.model"),
                'masked_key' => $this->settings->maskedKey($key),
                'price_in' => $this->settings->priceIn($key),
                'price_out' => $this->settings->priceOut($key),
                'key_source' => $this->settings->keySource($key),
                'ready' => $this->factory->make($key) !== null,
            ];
        }

        return view('manage.settings.ai-assistant', [
            'enabled' => $this->settings->enabled(),
            'primary' => $this->settings->providerOrder()[0] ?? 'anthropic',
            'providers' => $providers,
            'perCustomerPerHour' => $this->settings->perCustomerPerHour(),
            'perDayTotal' => $this->settings->perDayTotal(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'primary' => ['required', 'in:' . implode(',', AssistantSettings::PROVIDERS)],
            'model' => ['nullable', 'array'],
            'model.*' => ['nullable', 'string', 'max:100'],
            'key' => ['nullable', 'array'],
            'key.*' => ['nullable', 'string', 'max:300'],
            'per_customer_hour' => ['required', 'integer', 'min:0', 'max:1000'],
            'per_day_total' => ['required', 'integer', 'min:0', 'max:100000'],
            'price_in' => ['nullable', 'array'],
            'price_in.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_out' => ['nullable', 'array'],
            'price_out.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $primary = $data['primary'];
        $turningOn = $request->boolean('enabled');

        // Going live needs a working primary: refuse to switch on a provider with no key or model.
        if ($turningOn) {
            $typedKey = trim((string) ($data['key'][$primary] ?? ''));
            $typedModel = trim((string) ($data['model'][$primary] ?? ''));
            $hasKey = $typedKey !== '' || $this->settings->apiKey($primary) !== null;
            $hasModel = $typedModel !== '' || $this->settings->model($primary) !== null;

            if (! $hasKey || ! $hasModel) {
                return back()->withErrors([
                    'primary' => 'Turn the assistant on only after the primary provider has an API key and a model name.',
                ])->withInput();
            }
        }

        foreach (AssistantSettings::PROVIDERS as $key) {
            $this->settings->saveApiKey($key, $data['key'][$key] ?? null); // blank keeps the stored key
            $this->settings->saveModel($key, trim((string) ($data['model'][$key] ?? '')) ?: null);

            $in = $data['price_in'][$key] ?? null;
            $out = $data['price_out'][$key] ?? null;
            $this->settings->savePrices(
                $key,
                $in === null || $in === '' ? null : (float) $in,
                $out === null || $out === '' ? null : (float) $out,
            );
        }

        $this->settings->saveProviderOrder($primary);
        $this->settings->saveLimits((int) $data['per_customer_hour'], (int) $data['per_day_total']);
        $this->settings->setEnabled($turningOn);

        // Audit trail: who changed what. Never the key itself.
        Log::info('assistant.settings_updated', [
            'by' => $request->user()?->id,
            'enabled' => $turningOn,
            'primary' => $primary,
            'per_customer_hour' => (int) $data['per_customer_hour'],
            'per_day_total' => (int) $data['per_day_total'],
        ]);

        return redirect()->route('manage.ai-assistant.edit')
            ->with('ok', 'AI assistant settings saved. ' . ($turningOn ? 'The assistant is ON.' : 'The assistant is OFF.'));
    }

    /** Send one tiny prompt to the primary provider to prove the key and model work. */
    public function test()
    {
        $primary = $this->settings->providerOrder()[0] ?? null;
        $provider = $primary ? $this->factory->make($primary) : null;

        if ($provider === null) {
            return back()->withErrors(['primary' => 'The primary provider needs an API key and a model name first.']);
        }

        try {
            $reply = $provider->complete(
                (string) $this->settings->model($primary),
                'Reply with the single word OK.',
                'ping',
                10,
            );
        } catch (AiProviderException $e) {
            return back()->withErrors(['primary' => ucfirst($primary) . ' test failed: ' . $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withErrors(['primary' => ucfirst($primary) . ' test failed unexpectedly. Check the log.']);
        }

        return back()->with('ok', ucfirst($primary) . ' answered ✅ (model ' . $reply->model . ', ' . $reply->inputTokens . ' in / ' . $reply->outputTokens . ' out tokens).');
    }
}
