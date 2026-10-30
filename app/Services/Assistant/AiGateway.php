<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Log;

/**
 * Provider-neutral entry point (REQ-AI-020, REQ-AI-021). Tries each configured provider in
 * order and returns the first success. Any failure moves on to the next provider; if all fail
 * the caller gets AiUnavailableException and shows the REQ-AI-006 message.
 */
class AiGateway
{
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly ProviderFactory $factory,
    ) {}

    /** @throws AiUnavailableException */
    public function complete(string $system, string $user, int $maxTokens): AiReply
    {
        $tried = [];

        foreach ($this->settings->providerOrder() as $key) {
            $provider = $this->factory->make($key);
            if ($provider === null) {
                continue; // not configured yet: skip silently, staff see it on the settings page
            }

            $model = $this->settings->model($key);
            $tried[] = $key;

            try {
                return $provider->complete($model, $system, $user, $maxTokens);
            } catch (AiProviderException $e) {
                // Log the provider and status only: never the prompt or the key.
                Log::warning('assistant.provider_failed', [
                    'provider' => $key,
                    'retryable' => $e->retryable,
                    'status' => $e->status,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw new AiUnavailableException($tried === [] ? 'No AI provider is configured.' : 'All AI providers failed.');
    }
}
