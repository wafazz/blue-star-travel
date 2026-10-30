<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Services\Assistant\AiProviderException;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\ModelCatalog;
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
    /** Dropdown value meaning "type the model ID in the text box instead". */
    public const OTHER_MODEL = '__other__';

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
                'model_options' => array_values(array_unique(array_merge(
                    config("ai.drivers.{$key}.models", []),
                    $this->settings->discoveredModels($key),
                ))),
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
            'model_other' => ['nullable', 'array'],
            'model_other.*' => ['nullable', 'string', 'max:100'],
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

        // Resolve each model: a dropdown choice, or the typed ID when "Other" is chosen.
        $models = [];
        foreach (AssistantSettings::PROVIDERS as $key) {
            $choice = trim((string) ($data['model'][$key] ?? ''));
            $models[$key] = $choice === self::OTHER_MODEL
                ? trim((string) ($data['model_other'][$key] ?? ''))
                : $choice;
        }

        // Going live needs a working primary: refuse to switch on a provider with no key or model.
        if ($turningOn) {
            $typedKey = trim((string) ($data['key'][$primary] ?? ''));
            $typedModel = $models[$primary];
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
            $this->settings->saveModel($key, $models[$key] ?: null);

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

    /**
     * "Check for new models": asks one provider for its current chat models and ADDS any new ones
     * to the dropdown. It never switches the active model and never removes anything (add-only).
     */
    public function refresh(string $provider, ModelCatalog $catalog)
    {
        abort_unless(in_array($provider, AssistantSettings::PROVIDERS, true), 404);

        $label = config("ai.drivers.{$provider}.label");

        try {
            $found = $catalog->fetch($provider);
        } catch (AiProviderException $e) {
            return back()->withErrors([$provider => $label . ': ' . $e->getMessage()]);
        }

        $known = array_merge(config("ai.drivers.{$provider}.models", []), $this->settings->discoveredModels($provider));
        $new = array_values(array_diff($found, $known));

        $this->settings->saveDiscoveredModels($provider, array_merge($this->settings->discoveredModels($provider), $new));

        Log::info('assistant.models_checked', [
            'by' => request()->user()?->id,
            'provider' => $provider,
            'found' => count($found),
            'new' => count($new),
        ]);

        $message = $new
            ? count($new) . " new model(s) added to the {$label} list: " . implode(', ', $new)
                . '. Pick one and save the settings to use it.'
            : "{$label}: no new models. Your list is up to date.";

        return back()->with('ok', $message);
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
