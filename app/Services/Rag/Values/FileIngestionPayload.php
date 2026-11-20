<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * Backend-agnostic description of one original file to ingest into the
 * assistant's dataset. Carried when the RAG server runs its own file
 * conversion instead of receiving locally extracted text. The external
 * document id is the attachment uuid; backends that cannot key documents
 * by it (e.g. server-generated managed-document ids) should keep it in
 * the metadata for traceability. Wire formats are the concern of each
 * {@see \App\Services\Rag\Contracts\RagIngesterInterface} implementation.
 */
readonly class FileIngestionPayload
{
    public function __construct(
        public string $datasetId,
        public string $externalDocumentId,
        public string $filename,
        public string $mimeType,
        public string $content,
        public string $displayName,
        /** @var array<string, string|int|bool|null> */
        public array $metadata = [],
    ) {
    }
}
