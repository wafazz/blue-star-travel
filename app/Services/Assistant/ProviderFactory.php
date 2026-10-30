<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Providers\AiProvider;
use App\Services\Assistant\Providers\AnthropicProvider;
use App\Services\Assistant\Providers\GeminiProvider;
use App\Services\Assistant\Providers\OpenAiProvider;

/**
 * Builds the adapter for a provider key from current settings. Returns null when the
 * provider is not usable yet (no key, or no model set), so the gateway skips it.
 */
class ProviderFactory
{
    public function __construct(private readonly AssistantSettings $settings) {}

    public function make(string $key): ?AiProvider
    {
        $apiKey = $this->settings->apiKey($key);
        if (! $apiKey || ! $this->settings->model($key)) {
            return null;
        }

        $timeout = (float) config("ai.drivers.{$key}.timeout", 25);

        return match ($key) {
            'anthropic' => new AnthropicProvider($apiKey, $timeout),
            'openai' => new OpenAiProvider($apiKey, (string) config('ai.drivers.openai.base_url'), $timeout),
            'gemini' => new GeminiProvider($apiKey, (string) config('ai.drivers.gemini.base_url'), $timeout),
            default => null,
        };
    }
}
