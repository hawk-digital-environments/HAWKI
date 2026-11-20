<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * Result of one {@see \App\Services\Rag\Contracts\RagIngesterInterface::checkIngestion()}
 * poll: the outcome in HAWKI's vocabulary plus an optional human-readable
 * reason persisted as `rag_error` on terminal failure.
 */
readonly class RagIngestionCheck
{
    public function __construct(
        public RagIngestionOutcome $outcome,
        public string|null $reason = null,
    ) {
    }

    public static function running(): self
    {
        return new self(RagIngestionOutcome::RUNNING);
    }

    public static function succeeded(): self
    {
        return new self(RagIngestionOutcome::SUCCEEDED);
    }

    public static function failed(string $reason): self
    {
        return new self(RagIngestionOutcome::FAILED, $reason);
    }
}
