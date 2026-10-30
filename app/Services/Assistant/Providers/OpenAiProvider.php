<?php

namespace App\Services\Assistant\Providers;

use App\Services\Assistant\AiProviderException;
use App\Services\Assistant\AiReply;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI via Chat Completions over Laravel's HTTP client. OpenAI has no official Laravel
 * SDK, so raw HTTP is the documented exception in the claude-api skill's rules.
 */
class OpenAiProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly float $timeout = 25.0,
    ) {}

    public function key(): string
    {
        return 'openai';
    }

    public function complete(string $model, string $system, string $user, int $maxTokens): AiReply
    {
        if ($this->apiKey === '') {
            throw new AiProviderException('OpenAI API key is not set.', retryable: false);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'max_completion_tokens' => $maxTokens,
                ]);
        } catch (ConnectionException $e) {
            throw new AiProviderException('OpenAI timed out or is unreachable.', retryable: true);
        }

        $this->guard($response);

        $text = (string) data_get($response->json(), 'choices.0.message.content', '');

        return new AiReply(
            text: $text,
            provider: $this->key(),
            model: $model,
            inputTokens: (int) data_get($response->json(), 'usage.prompt_tokens', 0),
            outputTokens: (int) data_get($response->json(), 'usage.completion_tokens', 0),
        );
    }

    /** Throws a classified exception for any non-success status. */
    private function guard($response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $retryable = $status === 429 || $status >= 500;

        throw new AiProviderException("OpenAI returned HTTP {$status}.", retryable: $retryable, status: $status);
    }
}
