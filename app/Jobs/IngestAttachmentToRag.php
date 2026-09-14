<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Agents\Utils\ExtractTextCollector;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Exceptions\RagIngestionRequestException;
use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\RagIngestionOutcome;
use App\Services\Rag\Values\RagIngestionStatus;
use App\Services\Rag\Values\TextIngestionPayload;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Attributes\Config;
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
 * the locally extracted text to the text-ingestion endpoint; "file"
 * uploads the original file so the RAG server runs its own conversion
 * (uploading also replaces a previously ingested document via the stored
 * `rag_document_id` handle).
 *
 * State machine on the attachment row: pending -> ingesting -> ingested |
 * failed | skipped. Because the RAG server processes ingestion
 * asynchronously (Temporal workflow), the job re-releases itself with a
 * delay while the task is running instead of blocking a worker slot.
 * Attempts are unlimited but capped by retryUntil; permanent failure and
 * expiry both funnel into {@see failed()}.
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

    /** @return list<object> */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled()];
    }

    /** Unlimited attempts; expiry is governed by {@see retryUntil}. */
    public int $tries = 0;

    /** @var list<int> delays for retries after thrown exceptions */
    public array $backoff = [30, 60, 120, 300];

    private const int POLL_DELAY_SECONDS = 10;

    /** Hard text length limit of the RAG server per document, in characters. */
    private const int MAX_TEXT_LENGTH = 1_048_576;

    private const string FILE_INGESTION_MODE = 'file';

    public function __construct(
        public readonly int $assistantId,
        public readonly int $assistantAttachmentId,
    ) {
    }

    public function handle(
        AssistantAttachmentRepository $assistantAttachmentRepository,
        RagIngesterInterface $ingester,
        FileStorageService $fileStorage,
        ExtractTextCollector $extractTextCollector,
        #[Config('rag.dataset_prefix')]
        string $datasetPrefix,
        #[Config('rag.attachment_ingestion')]
        string $attachmentIngestion,
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

        $ingestsFiles = self::FILE_INGESTION_MODE === $attachmentIngestion;
        $datasetId = $datasetPrefix . $this->assistantId;

        try {
            if (RagIngestionStatus::INGESTING === $status) {
                $this->resolveCheck($assistantAttachmentRepository, $ingester, (string)$attachment->rag_task_id);

                return;
            }

            $file = $fileStorage->retrieve(StoredFileIdentifier::fromAssistantAttachment($attachment));

            if (null === $file) {
                $assistantAttachmentRepository->updateRagState(
                    $this->assistantAttachmentId,
                    RagIngestionStatus::SKIPPED,
                    error: 'Stored file is no longer available.',
                );

                return;
            }

            $text = '';

            if (!$ingestsFiles) {
                // File mode leaves conversion to the RAG server, so local
                // text extraction and its guards do not apply there.
                $text = $extractTextCollector->collect($file);

                if ('' === $text) {
                    $assistantAttachmentRepository->updateRagState(
                        $this->assistantAttachmentId,
                        RagIngestionStatus::SKIPPED,
                        error: 'File has no extractable text.',
                    );

                    return;
                }

                if (\mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                    $assistantAttachmentRepository->updateRagState(
                        $this->assistantAttachmentId,
                        RagIngestionStatus::FAILED,
                        error: \sprintf(
                            'Extracted text exceeds the RAG server limit of %d characters.',
                            self::MAX_TEXT_LENGTH,
                        ),
                    );

                    return;
                }
            }

            if (!$ingester->ensureDataset($datasetId)) {
                $assistantAttachmentRepository->updateRagState(
                    $this->assistantAttachmentId,
                    RagIngestionStatus::FAILED,
                    error: \sprintf('The RAG backend refused to provide dataset "%s".', $datasetId),
                );

                return;
            }

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
                $handle = $ingester->ingest($this->payload($datasetId, $attachment, $text), $this->idempotencyKey($file));
            }

            if ('' === $handle) {
                $assistantAttachmentRepository->updateRagState(
                    $this->assistantAttachmentId,
                    RagIngestionStatus::FAILED,
                    error: 'The configured RAG ingester returned no ingestion handle.',
                );

                return;
            }

            $assistantAttachmentRepository->updateRagState(
                $this->assistantAttachmentId,
                RagIngestionStatus::INGESTING,
                taskId: $handle,
                documentId: $documentId,
            );

            $this->resolveCheck($assistantAttachmentRepository, $ingester, $handle);
        } catch (RagIngestionRequestException $e) {
            if ($e->isTransient()) {
                $logger->warning('Transient RAG backend failure, retrying ingestion later', ['exception' => $e]);
                $this->release(self::POLL_DELAY_SECONDS);

                return;
            }

            $assistantAttachmentRepository->updateRagState(
                $this->assistantAttachmentId,
                RagIngestionStatus::FAILED,
                error: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            $logger->error('RAG ingestion failed unexpectedly, retrying later', ['exception' => $e]);
            $this->release(self::POLL_DELAY_SECONDS);
        }
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
            app(AssistantAttachmentRepository::class)->updateRagState(
                $this->assistantAttachmentId,
                RagIngestionStatus::FAILED,
                error: 'Ingestion job expired or failed permanently: ' . ($e?->getMessage() ?? 'unknown error'),
            );
        } catch (\Throwable $markFailure) {
            report($markFailure);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        // Migration-file-style exemption: the queue worker calls this on the
        // raw unserialized instance, where a injected clock is unavailable.
        return now()->addMinutes(30);
    }

    /**
     * Polls the ingestion once via the backend-agnostic contract: terminal
     * success marks the attachment ingested, terminal failure marks it
     * failed, anything else re-releases the job for the next poll.
     */
    private function resolveCheck(
        AssistantAttachmentRepository $assistantAttachmentRepository,
        RagIngesterInterface $ingester,
        string $handle,
    ): void {
        if ('' === $handle) {
            $assistantAttachmentRepository->updateRagState(
                $this->assistantAttachmentId,
                RagIngestionStatus::FAILED,
                error: 'Ingestion is marked running but no ingestion handle is stored.',
            );

            return;
        }

        $check = $ingester->checkIngestion($handle);

        if (RagIngestionOutcome::SUCCEEDED === $check->outcome) {
            $assistantAttachmentRepository->updateRagState($this->assistantAttachmentId, RagIngestionStatus::INGESTED);

            return;
        }

        if (RagIngestionOutcome::FAILED === $check->outcome) {
            $assistantAttachmentRepository->updateRagState(
                $this->assistantAttachmentId,
                RagIngestionStatus::FAILED,
                error: $check->reason ?? 'The RAG backend reported a terminal ingestion failure.',
            );

            return;
        }

        $this->release(self::POLL_DELAY_SECONDS);
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
