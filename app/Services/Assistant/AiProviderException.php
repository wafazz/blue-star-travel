<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * Failure from one provider. `retryable` tells the gateway whether the next provider in
 * the chain should be tried (timeout, quota, 5xx) or the failure is final (bad key, bad request).
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }
}
