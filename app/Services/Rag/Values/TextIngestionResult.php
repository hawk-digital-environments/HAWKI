<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * Result of a started text ingestion: the handle for completion polling
 * ({@see \App\Services\Rag\Contracts\RagIngesterInterface::checkIngestion()})
 * and the backend-assigned source id for later delete operations. Either
 * may be '' when the backend does not provide one.
 */
readonly class TextIngestionResult
{
    public function __construct(
        public string $taskId,
        public string $sourceId,
    ) {
    }

    public static function empty(): self
    {
        return new self('', '');
    }
}
