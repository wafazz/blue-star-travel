<?php

namespace App\Services\Assistant\Providers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Core\Exceptions\APIException;
use App\Services\Assistant\AiProviderException;
use App\Services\Assistant\AiReply;
use Throwable;

/**
 * Anthropic Claude through the official PHP SDK (anthropic-ai/sdk), as the claude-api
 * skill requires for a PHP project. The SDK's own retries are switched off: the gateway
 * decides when to retry or fall back, so one slow request never waits twice.
 */
class AnthropicProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly float $timeout = 25.0,
        private ?Client $client = null,
    ) {}

    public function key(): string
    {
        return 'anthropic';
    }

    public function complete(string $model, string $system, string $user, int $maxTokens): AiReply
    {
        if ($this->apiKey === '') {
            throw new AiProviderException('Anthropic API key is not set.', retryable: false);
        }

        try {
            $message = $this->client()->messages->create(
                maxTokens: $maxTokens,
                messages: [['role' => 'user', 'content' => $user]],
                model: $model,
                system: $system,
            );
        } catch (APIConnectionException|RateLimitException|InternalServerException $e) {
            // Timeouts, connection failures, 429 and 5xx: the next provider may succeed.
            throw new AiProviderException('Anthropic unavailable: ' . $e->getMessage(), retryable: true);
        } catch (AuthenticationException|BadRequestException|APIException $e) {
            // Bad key, unknown model ID, invalid request: retrying elsewhere will not fix config.
            throw new AiProviderException('Anthropic rejected the request: ' . $e->getMessage(), retryable: false);
        } catch (Throwable $e) {
            throw new AiProviderException('Anthropic call failed: ' . $e->getMessage(), retryable: true);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AiReply(
            text: $text,
            provider: $this->key(),
            model: $model,
            inputTokens: (int) $message->usage->inputTokens,
            outputTokens: (int) $message->usage->outputTokens,
        );
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: $this->apiKey,
            requestOptions: ['timeout' => $this->timeout, 'maxRetries' => 0],
        );
    }
}
