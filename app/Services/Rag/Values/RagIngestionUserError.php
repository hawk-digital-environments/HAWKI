<?php

declare(strict_types=1);

namespace App\Services\Rag\Values;

/**
 * User-facing reason an attachment's RAG ingestion did not succeed.
 *
 * The enum value IS a frontend translation key (`rag.ingestion.*` in
 * `resources/language/rag_*.json`): the pipeline persists it verbatim in
 * `assistant_attachments.rag_user_error`, the JSON:API schema passes it
 * through, and the UI resolves it with its translator — no mapping table
 * anywhere. Technical detail (exceptions, response bodies, task handles)
 * never travels this path; it goes to the backend logs and the admin-only
 * `rag_error` column instead.
 *
 * Older rows written before this enum exists carry no `rag_user_error`;
 * the frontend falls back to the generic {@see self::IngestionFailed}
 * message for them.
 */
enum RagIngestionUserError: string
{
    /** The assistant's RAG dataset could not be provisioned (creation or ingest grant refused). */
    case DatasetProvisioningFailed = 'rag.ingestion.dataset_error';

    /** The attachment's content could not be ingested into the knowledge base. */
    case IngestionFailed = 'rag.ingestion.file_error';

    /** The ingestion job expired (retryUntil) before reaching a terminal state. */
    case IngestionTimeout = 'rag.ingestion.timeout_error';

    /** Skipped: the stored file is gone, so there is nothing to ingest. */
    case StoredFileMissing = 'rag.ingestion.stored_file_missing_error';

    /** Skipped: the file has no extractable text for the text-ingestion mode. */
    case NoExtractableText = 'rag.ingestion.no_extractable_text_error';
}
