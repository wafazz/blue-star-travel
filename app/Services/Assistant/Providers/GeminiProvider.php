<?php

namespace App\Services\Assistant\Providers;

use App\Services\Assistant\AiProviderException;
use App\Services\Assistant\AiReply;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini via the generateContent REST endpoint. Google's Laravel-compatible SDK
 * path is not used; the key is sent in a header, never in the URL, so it is not logged.
 */
class GeminiProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        private readonly float $timeout = 25.0,
    ) {}

    public function key(): string
    {
        return 'gemini';
    }

    public function complete(string $model, string $system, string $user, int $maxTokens): AiReply
    {
        if ($this->apiKey === '') {
            throw new AiProviderException('Gemini API key is not set.', retryable: false);
        }

        $url = rtrim($this->baseUrl, '/') . '/models/' . rawurlencode($model) . ':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $user]]],
                    ],
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ]);
        } catch (ConnectionException $e) {
            throw new AiProviderException('Gemini timed out or is unreachable.', retryable: true);
        }

        if (! $response->successful()) {
            $status = $response->status();

            throw new AiProviderException(
                "Gemini returned HTTP {$status}.",
                retryable: $status === 429 || $status >= 500,
                status: $status,
            );
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $text = collect($parts)->pluck('text')->filter(fn ($t) => is_string($t))->implode('');

        return new AiReply(
            text: $text,
            provider: $this->key(),
            model: $model,
            inputTokens: (int) data_get($response->json(), 'usageMetadata.promptTokenCount', 0),
            outputTokens: (int) data_get($response->json(), 'usageMetadata.candidatesTokenCount', 0),
        );
    }
}
