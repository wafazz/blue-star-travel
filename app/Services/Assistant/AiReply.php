<?php

namespace App\Services\Assistant;

/** Plain result of one provider call. Carries the token counts REQ-AI-022 needs. */
final class AiReply
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
    ) {}
}
