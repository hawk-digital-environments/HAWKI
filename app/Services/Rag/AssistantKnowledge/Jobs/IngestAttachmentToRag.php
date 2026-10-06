<?php

declare(strict_types=1);

namespace App\Services\Rag\AssistantKnowledge\Jobs;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Agents\Utils\ExtractTextCollector;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Rag\AssistantKnowledge\Repositories\RagIngestionStateRepository;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Config\RagConfig;
use App\Services\Rag\Exceptions\RagIngestionRequestException;
use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\RagIngestionOutcome;
use App\Services\Rag\Values\RagIngestionStatus;
use App\Services\Rag\Values\RagIngestionUserError;
use App\Services\Rag\Values\TextIngestionPayload;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Psr\Log\LoggerInterface;

/**
 * Queued RAG ingestion of one assistant knowledge file.
 *
 * What is sent is governed by `rag.attachment_ingestion`: "text" pushes
 * the locally extracted text to the text-ingestion endpoint (persisting
 * the returned `source_*` handle in `rag_document_id`); "file" uploads
 * the original file so the RAG server runs its own conversion (uploading
 * also replaces a previously ingested document via the stored
 * `rag_document_id` handle).
 *
 * The pipeline runs in stages — stored-file retrieval, text extraction,
 * dataset provisioning, ingestion push, ingestion polling — and each
 * stage owns its failures: transient backend errors release the job for
 * a retry (warning log), permanent ones resolve the attachment as
 * FAILED/SKIPPED with a {@see RagIngestionUserError} code for the user
 * and a technical `rag_error` detail plus an error log for admins. Raw
 * exception detail never travels towards the user.
 *
 * State machine on the attachment row: pending -> ingesting -> ingested |
 * failed | skipped. Because the RAG server processes ingestion
 * asynchronously (Temporal workflow), the job re-releases itself with a
 * delay while the task is running instead of blocking a worker slot.
 * Attempts are unlimited but capped by retryUntil; permanent failure and
 * expiry both funnel into {@see failed()} / the stage error mapping.
 *
 * The job travels inside a batch so deleting the attachment can cancel
 * it: once the batch is cancelled, {@see SkipIfBatchCancelled} makes the
 * worker discard a still-queued run without executing it (works on any
 * queue driver).
 */
class IngestAttachmentToRag implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int POLL_DELAY_SECONDS = 10;

    private const string FILE_INGESTION_MODE = 'file';

    /** Technical detail longer than this is trimmed for the rag_error column (logs keep the full exception). */
    private const int RAG_ERROR_MAX_LENGTH = 500;

    /** @return list<object> */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled()];
    }

    /** Unlimited attempts; expiry is governed by {@see retryUntil}. */
    public int $tries = 0;

    /** @var list<int> delays for retries after thrown exceptions */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public readonly int $assistantId,
        public readonly int $assistantAttachmentId,
    ) {
    }

    public function handle(
        AssistantAttachmentRepository $assistantAttachmentRepository,
        RagIngestionStateRepository $ragState,
        RagIngesterInterface $ingester,
        FileStorageService $fileStorage,
        ExtractTextCollector $extractTextCollector,
        RagConfig $config,
        LoggerInterface $logger,
    ): void {
        $attachment = $assistantAttachmentRepository->findOne($this->assistantAttachmentId);

        if (null === $attachment) {
            // Deleted meanwhile — nothing to ingest.
            return;
        }

        $status = $attachment->rag_status;

        if (null === $status || !$status->isActive()) {
            // Already resolved (ingested/failed/skipped) or RAG not applicable.
            return;
        }

        $context = $this->logContext($attachment);

        if (RagIngestionStatus::INGESTING === $status) {
            $this->pollIngestion($ragState, $ingester, $logger, (string)$attachment->rag_task_id, $context);

            return;
        }

        $file = $this->retrieveStoredFile($ragState, $fileStorage, $logger, $attachment, $context);

        if (null === $file) {
            return;
        }

        $text = '';

        if (self::FILE_INGESTION_MODE !== $config->attachmentIngestion) {
            // File mode leaves conversion to the RAG server, so local
            // text extraction and its guards do not apply there.
            $text = $this->extractText($ragState, $extractTextCollector, $logger, $file, $context);

            if (null === $text) {
                return;
            }
        }

        $datasetId = $config->datasetPrefix . $this->assistantId;

        if (!$this->provisionDataset($ragState, $ingester, $logger, $datasetId, $context)) {
            return;
        }

        $this->pushForIngestion(
            $ragState,
            $ingester,
            $logger,
            $datasetId,
            $attachment,
            $file,
            $text,
            self::FILE_INGESTION_MODE === $config->attachmentIngestion,
            $context,
        );
    }

    /**
     * Called by the queue worker when the job dies permanently (expiry via
     * retryUntil, fatal error after all retries). Constructor-injected
     * collaborators are not available on the unserialized instance, hence
     * the service locator.
     */
    public function failed(?\Throwable $e = null): void
    {
        try {
            app(RagIngestionStateRepository::class)->updateState(
                $this->assistantAttachmentId,
                RagIngestionStatus::FAILED,
                error: $this->technicalError($e),
                userError: RagIngestionUserError::IngestionTimeout,
            );
        } catch (\Throwable $markFailure) {
            report($markFailure);
        }

        if (null !== $e) {
            report($e);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        // Migration-file-style exemption: the queue worker calls this on the
        // raw unserialized instance, where a injected clock is unavailable.
        return now()->addMinutes(30);
    }

    // --- Stages ------------------------------------------------------------

    /**
     * Stage: retrieves the stored file. A vanished file resolves the
     * attachment as SKIPPED; storage errors are transient and retry the job.
     */
    private function retrieveStoredFile(
        RagIngestionStateRepository $ragState,
        FileStorageService $fileStorage,
        LoggerInterface $logger,
        AssistantAttachment $attachment,
        array $context,
    ): ?StoredFile {
        try {
            $file = $fileStorage->retrieve(StoredFileIdentifier::fromAssistantAttachment($attachment));
        } catch (\Throwable $e) {
            $this->retryLater($logger, 'retrieve-stored-file', $e, $context);

            return null;
        }

        if (null === $file) {
            $logger->notice('RAG ingestion skipped: the stored file is no longer available', $context);
            $this->skip($ragState, RagIngestionUserError::StoredFileMissing);
        }

        return $file;
    }

    /**
     * Stage: extracts the text for text-mode ingestion. Files without
     * extractable text resolve the attachment as SKIPPED; extraction
     * errors are transient and retry the job. Returns null when the
     * pipeline should not continue.
     */
    private function extractText(
        RagIngestionStateRepository $ragState,
        ExtractTextCollector $extractTextCollector,
        LoggerInterface $logger,
        StoredFile $file,
        array $context,
    ): ?string {
        try {
            $text = $extractTextCollector->collect($file);
        } catch (\Throwable $e) {
            $this->retryLater($logger, 'extract-text', $e, $context);

            return null;
        }

        if ('' === $text) {
            $logger->notice('RAG ingestion skipped: the file has no extractable text', $context);
            $this->skip($ragState, RagIngestionUserError::NoExtractableText);

            return null;
        }

        return $text;
    }

    /**
     * Stage: provisions the assistant's RAG dataset (check-then-create plus
     * the ingest grant). A refusal or permanent backend rejection resolves
     * the attachment as FAILED with the dataset error code — this is an
     * infrastructure concern the user cannot fix by retrying. Transient
     * backend errors retry the job. Returns whether the pipeline should
     * continue.
     */
    private function provisionDataset(
        RagIngestionStateRepository $ragState,
        RagIngesterInterface $ingester,
        LoggerInterface $logger,
        string $datasetId,
        array $context,
    ): bool {
        $context = $context + ['dataset_id' => $datasetId];

        try {
            $provisioned = $ingester->ensureDataset($datasetId);
        } catch (RagIngestionRequestException $e) {
            if ($e->isTransient()) {
                $this->retryLater($logger, 'provision-dataset', $e, $context);

                return false;
            }

            $logger->error('RAG dataset provisioning was rejected by the backend', ['exception' => $e] + $context);
            $this->fail($ragState, RagIngestionUserError::DatasetProvisioningFailed, $e);

            return false;
        } catch (\Throwable $e) {
            $this->retryLater($logger, 'provision-dataset', $e, $context);

            return false;
        }

        if (!$provisioned) {
            $logger->error('RAG dataset provisioning was refused without an error', $context);
            $this->fail($ragState, RagIngestionUserError::DatasetProvisioningFailed, null, \sprintf('The RAG backend refused to provide dataset "%s".', $datasetId));

            return false;
        }

        return true;
    }

    /**
     * Stage: pushes text or file to the RAG server and moves the attachment
     * to INGESTING. Permanent backend rejections and unusable responses
     * resolve the attachment as FAILED with the ingestion error code;
     * transient errors retry the job.
     */
    private function pushForIngestion(
        RagIngestionStateRepository $ragState,
        RagIngesterInterface $ingester,
        LoggerInterface $logger,
        string $datasetId,
        AssistantAttachment $attachment,
        StoredFile $file,
        string $text,
        bool $ingestsFiles,
        array $context,
    ): void {
        $context = $context + ['dataset_id' => $datasetId];

        try {
            $documentId = null;

            if ($ingestsFiles) {
                $result = $ingester->ingestFile(
                    $this->filePayload($datasetId, $attachment, $file),
                    $this->idempotencyKey($file),
                    $attachment->rag_document_id ?: null,
                );
                $handle = $result->taskId;
                $documentId = $result->documentId;
            } else {
                // Text ingestions are keyed server-side by a source id
                // (`source_*`); persisting it routes later deletions to the
                // text-ingestion endpoint.
                $result = $ingester->ingest($this->payload($datasetId, $attachment, $text), $this->idempotencyKey($file));
                $handle = $result->taskId;
                $documentId = $result->sourceId;
            }
        } catch (RagIngestionRequestException $e) {
            if ($e->isTransient()) {
                $this->retryLater($logger, 'push-ingestion', $e, $context);

                return;
            }

            $logger->error('RAG ingestion push was rejected by the backend', ['exception' => $e] + $context);
            $this->fail($ragState, RagIngestionUserError::IngestionFailed, $e);

            return;
        } catch (\Throwable $e) {
            $this->retryLater($logger, 'push-ingestion', $e, $context);

            return;
        }

        if ('' === $handle) {
            $logger->error('RAG ingestion push returned no ingestion handle', $context);
            $this->fail($ragState, RagIngestionUserError::IngestionFailed, null, 'The configured RAG ingester returned no ingestion handle.');

            return;
        }

        $ragState->updateState(
            $this->assistantAttachmentId,
            RagIngestionStatus::INGESTING,
            taskId: $handle,
            documentId: $documentId,
        );

        $this->pollIngestion($ragState, $ingester, $logger, $handle, $context);
    }

    /**
     * Stage: polls the ingestion once via the backend-agnostic contract:
     * terminal success marks the attachment ingested, terminal failure
     * marks it failed with the ingestion error code, anything else
     * re-releases the job for the next poll. Transient polling errors
     * retry the job.
     */
    private function pollIngestion(
        RagIngestionStateRepository $ragState,
        RagIngesterInterface $ingester,
        LoggerInterface $logger,
        string $handle,
        array $context,
    ): void {
        if ('' === $handle) {
            $logger->error('RAG ingestion is marked running but no ingestion handle is stored', $context);
            $this->fail($ragState, RagIngestionUserError::IngestionFailed, null, 'Ingestion is marked running but no ingestion handle is stored.');

            return;
        }

        $context = $context + ['rag_task_id' => $handle];

        try {
            $check = $ingester->checkIngestion($handle);
        } catch (RagIngestionRequestException $e) {
            if ($e->isTransient()) {
                $this->retryLater($logger, 'poll-ingestion', $e, $context);

                return;
            }

            $logger->error('RAG ingestion polling was rejected by the backend', ['exception' => $e] + $context);
            $this->fail($ragState, RagIngestionUserError::IngestionFailed, $e);

            return;
        } catch (\Throwable $e) {
            $this->retryLater($logger, 'poll-ingestion', $e, $context);

            return;
        }

        if (RagIngestionOutcome::SUCCEEDED === $check->outcome) {
            $logger->info('RAG ingestion completed', $context);
            $ragState->updateState($this->assistantAttachmentId, RagIngestionStatus::INGESTED);

            return;
        }

        if (RagIngestionOutcome::FAILED === $check->outcome) {
            $reason = $check->reason ?? 'The RAG backend reported a terminal ingestion failure.';
            $logger->error('RAG ingestion task failed on the backend', ['reason' => $reason] + $context);
            $this->fail($ragState, RagIngestionUserError::IngestionFailed, null, $reason);

            return;
        }

        $this->release(self::POLL_DELAY_SECONDS);
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * Resolves the attachment as FAILED: the user error code for the UI,
     * the technical detail (trimmed) for the admin-facing rag_error column.
     */
    private function fail(
        RagIngestionStateRepository $ragState,
        RagIngestionUserError $userError,
        ?\Throwable $e,
        ?string $detail = null,
    ): void {
        $ragState->updateState(
            $this->assistantAttachmentId,
            RagIngestionStatus::FAILED,
            error: $e !== null ? $this->technicalError($e) : $detail,
            userError: $userError,
        );
    }

    /** Resolves the attachment as SKIPPED — nothing was or will be ingested. */
    private function skip(RagIngestionStateRepository $ragState, RagIngestionUserError $userError): void
    {
        $ragState->updateState(
            $this->assistantAttachmentId,
            RagIngestionStatus::SKIPPED,
            userError: $userError,
        );
    }

    /** Logs a transient stage failure (warning) and re-releases the job. */
    private function retryLater(LoggerInterface $logger, string $stage, \Throwable $e, array $context): void
    {
        $logger->warning('Transient RAG backend failure, retrying ingestion later', ['exception' => $e, 'stage' => $stage] + $context);
        $this->release(self::POLL_DELAY_SECONDS);
    }

    /** @return array<string, int|string> */
    private function logContext(AssistantAttachment $attachment): array
    {
        return [
            'assistant_id' => $this->assistantId,
            'attachment_id' => $this->assistantAttachmentId,
            'attachment_uuid' => $attachment->uuid,
        ];
    }

    /** Trimmed exception message for the rag_error column; logs keep the full exception. */
    private function technicalError(?\Throwable $e): string
    {
        $message = $e?->getMessage() ?? 'unknown error';

        return \mb_substr($message, 0, self::RAG_ERROR_MAX_LENGTH);
    }

    private function payload(string $datasetId, AssistantAttachment $attachment, string $text): TextIngestionPayload
    {
        return new TextIngestionPayload(
            datasetId: $datasetId,
            externalDocumentId: $attachment->uuid,
            text: $text,
            displayName: \mb_substr($attachment->name, 0, 255),
            metadata: $this->metadata($attachment),
        );
    }

    private function filePayload(string $datasetId, AssistantAttachment $attachment, StoredFile $file): FileIngestionPayload
    {
        return new FileIngestionPayload(
            datasetId: $datasetId,
            externalDocumentId: $attachment->uuid,
            filename: $file->getOriginalFilename(),
            mimeType: $file->getMimeType(),
            content: $file->getContent(),
            displayName: \mb_substr($attachment->name, 0, 255),
            metadata: $this->metadata($attachment),
        );
    }

    /** @return array<string, string|int|bool|null> */
    private function metadata(AssistantAttachment $attachment): array
    {
        return [
            // The clean source name: the RAG indexer prefers a caller-provided
            // title over deriving one from converter artifact paths (which
            // carry chunk suffixes), so retrieval hits and the tool result's
            // documents list name the document like the attachment does.
            'title' => \mb_substr($attachment->name, 0, 255),
            'assistant_id' => $this->assistantId,
            'attachment_uuid' => $attachment->uuid,
            'mime' => $attachment->mime,
            'uploaded_by' => $attachment->user_id,
        ];
    }

    private function idempotencyKey(StoredFile $file): string
    {
        return 'attachment-' . $file->getUuid() . '-' . $file->getEtag();
    }
}
