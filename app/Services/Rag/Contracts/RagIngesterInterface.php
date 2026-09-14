<?php

declare(strict_types=1);

namespace App\Services\Rag\Contracts;

use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\FileIngestionResult;
use App\Services\Rag\Values\RagIngestionCheck;
use App\Services\Rag\Values\TextIngestionPayload;

/**
 * Backend-agnostic ingestion contract for RAG systems.
 *
 * One dataset (= vector store) per assistant, named `{dataset_prefix}{assistant-id}`
 * (e.g. `assistant_42`). Documents are keyed by the attachment uuid as the
 * external document id, so a re-ingest replaces the previous version.
 *
 * Implementations encapsulate everything backend-specific — wire formats,
 * status vocabularies, and polling mechanics. `ingest()` returns an opaque
 * handle the caller persists (`rag_task_id`) and feeds back into
 * {@see checkIngestion()} until it reports a terminal outcome.
 *
 * Error semantics are part of the contract: transient failures (timeouts,
 * refused connections, 5xx) surface as the HTTP client's own exceptions or
 * {@see \App\Services\Rag\Exceptions\RagIngestionRequestException::isTransient()}
 * ones, so callers can retry; permanent failures throw a non-transient
 * {@see \App\Services\Rag\Exceptions\RagIngestionRequestException}.
 */
interface RagIngesterInterface
{
    /**
     * Provisions the dataset for an assistant (check-then-create). Returns
     * whether the dataset is ready to ingest; `false` means a permanent,
     * server-side rejection.
     */
    public function ensureDataset(string $datasetId): bool;

    /**
     * Pushes the extracted text of one attachment into its dataset and
     * starts processing. Returns an opaque handle for completion polling
     * ('' when the ingester cannot provide one).
     */
    public function ingest(TextIngestionPayload $payload, string $idempotencyKey): string;

    /**
     * Uploads the original file of one attachment into its dataset and
     * starts server-side processing. When a non-empty
     * $existingDocumentId (from a previous file ingestion) is passed, the
     * document is replaced instead of created anew. Returns the polling
     * handle plus the backend-assigned document id.
     */
    public function ingestFile(FileIngestionPayload $payload, string $idempotencyKey, ?string $existingDocumentId = null): FileIngestionResult;

    /**
     * Polls the state of a previously started ingestion once. The mapping
     * from backend-specific status vocabulary to
     * {@see RagIngestionCheck} lives inside the implementation.
     */
    public function checkIngestion(string $handle): RagIngestionCheck;

    /**
     * Removes one document (by attachment uuid) from a dataset. Best-effort:
     * failures return false and are only logged as warnings by the caller.
     */
    public function deleteDocument(string $datasetId, string $externalDocumentId): bool;
}
