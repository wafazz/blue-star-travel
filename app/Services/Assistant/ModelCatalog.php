<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches the current model list from a provider, for the "Check for new models" button.
 * Only text-chat models are returned: image, audio, speech, embedding and similar models
 * are filtered out, because the assistant cannot use them.
 */
class ModelCatalog
{
    /** Words in a model ID that mean "not a chat model". */
    private const NON_CHAT = '/(image|audio|realtime|tts|transcribe|embedding|moderation|whisper|dall|search|cyber|research|live|robotics|computer|nano|banana|lyria|imagen|veo)/i';

    public function __construct(private readonly AssistantSettings $settings) {}

    /**
     * @return array<int, string> model IDs, sorted
     *
     * @throws AiProviderException when the key is missing or the provider cannot be reached
     */
    public function fetch(string $provider): array
    {
        $key = $this->settings->apiKey($provider);
        if (! $key) {
            throw new AiProviderException('Save an API key first, then check for models.', retryable: false);
        }

        $ids = match ($provider) {
            'anthropic' => $this->anthropic($key),
            'openai' => $this->openai($key),
            'gemini' => $this->gemini($key),
            default => throw new AiProviderException('Unknown provider.', retryable: false),
        };

        $ids = array_values(array_unique(array_filter($ids, fn (string $id) => ! preg_match(self::NON_CHAT, $id))));
        sort($ids);

        return $ids;
    }

    /** @return array<int, string> */
    private function anthropic(string $key): array
    {
        try {
            $client = new Client(apiKey: $key, requestOptions: ['timeout' => 25.0, 'maxRetries' => 0]);
            $page = $client->models->list(limit: 100);
        } catch (APIException|Throwable $e) {
            throw new AiProviderException('Anthropic model list failed: ' . $e->getMessage(), retryable: true);
        }

        $ids = [];
        foreach ($page->getItems() as $model) {
            // Skip retired models and anything that is not a Claude model.
            if ($model->deprecatedAt === null && str_starts_with($model->id, 'claude-') && ! str_contains($model->id, 'mythos')) {
                $ids[] = $model->id;
            }
        }

        return $ids;
    }

    /** @return array<int, string> */
    private function openai(string $key): array
    {
        $response = $this->get(
            rtrim((string) config('ai.drivers.openai.base_url'), '/') . '/models',
            ['Authorization' => 'Bearer ' . $key],
        );

        return collect(data_get($response->json(), 'data', []))
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && str_starts_with($id, 'gpt-'))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function gemini(string $key): array
    {
        $response = $this->get(
            rtrim((string) config('ai.drivers.gemini.base_url'), '/') . '/models?pageSize=1000',
            ['x-goog-api-key' => $key],
        );

        return collect(data_get($response->json(), 'models', []))
            // Keep only models that can generate text (not embedding-only models).
            ->filter(fn ($m) => in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true))
            ->map(fn ($m) => preg_replace('#^models/#', '', (string) ($m['name'] ?? '')))
            ->filter(fn ($id) => str_starts_with($id, 'gemini-'))
            ->values()
            ->all();
    }

    private function get(string $url, array $headers): \Illuminate\Http\Client\Response
    {
        try {
            $response = Http::withHeaders($headers)->acceptJson()->timeout(25)->get($url);
        } catch (ConnectionException) {
            throw new AiProviderException('Provider timed out or is unreachable.', retryable: true);
        }

        if (! $response->successful()) {
            $status = $response->status();

            throw new AiProviderException("Provider returned HTTP {$status}.", retryable: $status === 429 || $status >= 500, status: $status);
        }

        return $response;
    }
}
