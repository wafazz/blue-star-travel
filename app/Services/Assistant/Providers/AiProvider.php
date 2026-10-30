<?php

namespace App\Services\Assistant\Providers;

use App\Services\Assistant\AiReply;
use App\Services\Assistant\AiProviderException;

/**
 * One AI vendor behind the provider-neutral gateway (REQ-AI-020).
 * Business logic never calls a vendor SDK or HTTP endpoint directly.
 */
interface AiProvider
{
    /** Stable key used in settings and logs: anthropic | openai | gemini. */
    public function key(): string;

    /**
     * Send one system instruction and one user message.
     *
     * @throws AiProviderException  retryable=true for timeout, 429 and 5xx; false for bad config or 4xx
     */
    public function complete(string $model, string $system, string $user, int $maxTokens): AiReply;
}
