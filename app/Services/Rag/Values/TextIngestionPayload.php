<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * Backend-agnostic description of one document to ingest into the
 * assistant's dataset. The external document id is the attachment uuid, so
 * a re-ingest with a new idempotency key replaces the previous version.
 * Wire formats are the concern of each
 * {@see \App\Services\Rag\Contracts\RagIngesterInterface} implementation.
 */
readonly class TextIngestionPayload
{
    public function __construct(
        public string $datasetId,
        public string $externalDocumentId,
        public string $text,
        public string $displayName,
        /** @var array<string, string|int|bool|null> */
        public array $metadata = [],
        public string $contentFormat = 'markdown',
    ) {
    }
}
