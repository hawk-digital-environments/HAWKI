<?php

declare(strict_types=1);

namespace App\Services\Rag\Implementations;

use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\FileIngestionResult;
use App\Services\Rag\Values\RagIngestionCheck;
use App\Services\Rag\Values\TextIngestionPayload;

/**
 * No-op ingester bound when `rag.driver` resolves to nothing usable. Guards
 * a misconfiguration (RAG enabled but no driver): dataset provisioning never
 * fails an upload, and an ingestion attempt fails loudly (empty handle)
 * instead of silently pretending success.
 */
class NullRagIngester implements RagIngesterInterface
{
    public function ensureDataset(string $datasetId): bool
    {
        return true;
    }

    public function ingest(TextIngestionPayload $payload, string $idempotencyKey): string
    {
        return '';
    }

    public function ingestFile(FileIngestionPayload $payload, string $idempotencyKey, ?string $existingDocumentId = null): FileIngestionResult
    {
        return FileIngestionResult::empty();
    }

    public function checkIngestion(string $handle): RagIngestionCheck
    {
        return RagIngestionCheck::failed('The configured RAG ingester is a no-op.');
    }

    public function deleteDocument(string $datasetId, string $externalDocumentId): bool
    {
        return true;
    }
}
