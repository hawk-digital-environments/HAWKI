<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * Result of a started file ingestion: the handle for completion polling
 * ({@see \App\Services\Rag\Contracts\RagIngesterInterface::checkIngestion()})
 * and the backend-assigned document id for later replace/delete
 * operations. Either may be '' when the backend does not provide one.
 */
readonly class FileIngestionResult
{
    public function __construct(
        public string $taskId,
        public string $documentId,
    ) {
    }

    public static function empty(): self
    {
        return new self('', '');
    }
}
