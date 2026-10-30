<?php

namespace App\Services\Assistant;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Reads and writes the assistant configuration HQ edits in the admin panel (REQ-AI-031, OQ-1, OQ-2, OQ-4).
 *
 * Precedence: value saved in HQ settings > value in config/ai.php (which reads .env).
 * API keys are stored encrypted with the app key and are never returned to a browser in full.
 */
class AssistantSettings
{
    public const PROVIDERS = ['anthropic', 'openai', 'gemini'];

    public function enabled(): bool
    {
        $saved = Setting::get('assistant.enabled');

        return $saved !== null ? $saved === '1' : (bool) config('ai.enabled');
    }

    public function setEnabled(bool $on): void
    {
        Setting::put('assistant.enabled', $on ? '1' : '0');
    }

    /** Primary first, then fallbacks. Unknown keys are dropped. */
    public function providerOrder(): array
    {
        $saved = Setting::get('assistant.providers');
        $order = $saved ? json_decode($saved, true) : config('ai.providers');

        return array_values(array_filter(
            is_array($order) ? $order : [],
            fn ($key) => in_array($key, self::PROVIDERS, true),
        ));
    }

    public function saveProviderOrder(string $primary): void
    {
        $rest = array_values(array_diff(self::PROVIDERS, [$primary]));
        Setting::put('assistant.providers', json_encode([$primary, ...$rest]));
    }

    public function model(string $provider): ?string
    {
        $saved = Setting::get("assistant.model.{$provider}");
        $value = $saved !== null && $saved !== '' ? $saved : config("ai.drivers.{$provider}.model");

        return $value ? (string) $value : null;
    }

    public function saveModel(string $provider, ?string $model): void
    {
        Setting::put("assistant.model.{$provider}", (string) $model);
    }

    /** Decrypted key, or the env fallback. Null when none is configured. */
    public function apiKey(string $provider): ?string
    {
        $stored = Setting::get("assistant.key.{$provider}");
        if ($stored) {
            try {
                return Crypt::decryptString($stored);
            } catch (Throwable) {
                // Key rotated or row corrupt: fall through to env rather than fail the request.
            }
        }

        return config("ai.drivers.{$provider}.api_key") ?: null;
    }

    /** Store a new key (encrypted). Blank input keeps the existing key. */
    public function saveApiKey(string $provider, ?string $plain): void
    {
        if ($plain === null || $plain === '') {
            return;
        }
        Setting::put("assistant.key.{$provider}", Crypt::encryptString(trim($plain)));
    }

    /** What the admin page shows: the last 4 characters only. */
    public function maskedKey(string $provider): ?string
    {
        $key = $this->apiKey($provider);
        if (! $key) {
            return null;
        }

        return str_repeat('•', 8) . substr($key, -4);
    }

    public function keySource(string $provider): string
    {
        return Setting::get("assistant.key.{$provider}") ? 'settings' : (config("ai.drivers.{$provider}.api_key") ? 'env' : 'none');
    }

    /** Price per 1M input tokens as entered by HQ (currency is whatever HQ uses). Null when not set. */
    public function priceIn(string $provider): ?float
    {
        return $this->priceValue($provider, 'input');
    }

    public function priceOut(string $provider): ?float
    {
        return $this->priceValue($provider, 'output');
    }

    public function savePrices(string $provider, ?float $inPerMillion, ?float $outPerMillion): void
    {
        Setting::put("assistant.price.{$provider}.input", $inPerMillion === null ? '' : (string) $inPerMillion);
        Setting::put("assistant.price.{$provider}.output", $outPerMillion === null ? '' : (string) $outPerMillion);
    }

    private function priceValue(string $provider, string $side): ?float
    {
        $value = Setting::get("assistant.price.{$provider}.{$side}");

        return $value === null || $value === '' ? null : (float) $value;
    }

    public function perCustomerPerHour(): int
    {
        return (int) (Setting::get('assistant.limit.per_customer_hour') ?? config('ai.limits.per_customer_hour'));
    }

    public function perDayTotal(): int
    {
        return (int) (Setting::get('assistant.limit.per_day_total') ?? config('ai.limits.per_day_total'));
    }

    public function saveLimits(int $perCustomerPerHour, int $perDayTotal): void
    {
        Setting::put('assistant.limit.per_customer_hour', (string) $perCustomerPerHour);
        Setting::put('assistant.limit.per_day_total', (string) $perDayTotal);
    }
}
