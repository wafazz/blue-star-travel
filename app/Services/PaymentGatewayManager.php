<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Gateways\PaymentGatewayDriver;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Throwable;

class PaymentGatewayManager
{
    /** Settings that hold secrets — encrypted at rest, never echoed back to a browser. */
    const SECRET_FIELDS = ['key', 'x_signature'];

    /** @var array<string, PaymentGatewayDriver> */
    private array $resolved = [];

    public function default(): PaymentGatewayDriver
    {
        return $this->driver($this->defaultKey());
    }

    /** Active driver — the admin screen wins over the .env fallback. */
    public function defaultKey(): string
    {
        return (string) (Setting::get('payment.driver') ?: config('payments.default', 'sandbox'));
    }

    public function driver(string $key): PaymentGatewayDriver
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $config = $this->configFor($key);
        if (! $config || empty($config['class'])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return $this->resolved[$key] = new $config['class']($config);
    }

    /**
     * Driver config: file/env defaults, overlaid with anything saved from the
     * Payment Gateway Configuration screen.
     */
    public function configFor(string $key): array
    {
        $config = config('payments.drivers.' . $key);
        if (! $config) {
            return [];
        }

        foreach (['key', 'x_signature', 'collection_id', 'sandbox'] as $field) {
            $saved = $this->setting($key, $field);
            if ($saved !== null && $saved !== '') {
                $config[$field] = $field === 'sandbox' ? filter_var($saved, FILTER_VALIDATE_BOOLEAN) : $saved;
            }
        }

        return $config;
    }

    public function setting(string $driver, string $field): ?string
    {
        $value = Setting::get($this->settingKey($driver, $field));
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, self::SECRET_FIELDS, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable $e) {
                return null; // key rotated or corrupt — fall back to env
            }
        }

        return $value;
    }

    public function saveSetting(string $driver, string $field, ?string $value): void
    {
        $key = $this->settingKey($driver, $field);

        if ($value === null || $value === '') {
            Setting::where('key', $key)->delete();

            return;
        }

        Setting::put($key, in_array($field, self::SECRET_FIELDS, true) ? Crypt::encryptString($value) : $value);
    }

    public function settingKey(string $driver, string $field): string
    {
        return "payment.{$driver}.{$field}";
    }

    /** True when the active driver is a real vendor (not the local simulator). */
    public function isLive(): bool
    {
        return $this->defaultKey() !== 'sandbox';
    }

    /** Drivers available to choose from, keyed by config name. */
    public function available(): array
    {
        return collect(config('payments.drivers', []))
            ->map(fn ($c, $k) => $c['label'] ?? $k)->all();
    }

    /** Show a secret as ••••1234 so staff can tell it is set without exposing it. */
    public function mask(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return str_repeat('•', 8) . substr($value, -4);
    }
}
