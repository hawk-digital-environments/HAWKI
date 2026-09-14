<?php

declare(strict_types=1);

namespace App\Services\Rag\Implementations;

use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Exceptions\RagIngestionRequestException;
use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\FileIngestionResult;
use App\Services\Rag\Values\RagIngestionCheck;
use App\Services\Rag\Values\RagIngestionOutcome;
use App\Services\Rag\Values\TextIngestionPayload;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Http;

/**
 * HAWKI-RAG implementation of the ingestion contract: a REST client for the
 * external HAWKI-RAG server (Laravel + Temporal).
 *
 * Wire specifics that live here and nowhere else: the JSON payload shape of
 * the text-ingestion endpoint, the `Idempotency-Key` header semantics, the
 * Temporal task endpoint, and the mapping from task status strings to
 * {@see RagIngestionOutcome}.
 *
 * Transport-level failures (timeouts, refused connections) bubble up as the
 * Http client's own exceptions so callers can retry them; semantic 4xx
 * failures throw {@see RagIngestionRequestException}.
 */
class HawkiRagIngester implements RagIngesterInterface
{
    private const array TERMINAL_SUCCESS_STATUSES = ['succeeded', 'completed', 'done'];

    private const array TERMINAL_FAILURE_STATUSES = ['failed', 'error', 'cancelled', 'canceled'];

    public function __construct(
        #[Config('rag.api_url')]
        private string $apiUrl,
        #[Config('rag.api_key')]
        private string $apiKey,
        #[Config('rag.timeout')]
        private int $timeout,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function ensureDataset(string $datasetId): bool
    {
        if ($this->datasetExists($datasetId)) {
            return true;
        }

        $url = "{$this->apiUrl}/datasets";

        $response = $this->request()->post($url, [
            'dataset_id' => $datasetId,
        ]);

        if ($response->status() >= 500) {
            throw RagIngestionRequestException::forFailedResponse('POST', $url, $response->status(), $response->body());
        }

        if (409 === $response->status()) {
            // Conflicting metadata or a create race: an existing dataset we
            // can now see means ready, otherwise the conflict is permanent.
            return $this->datasetExists($datasetId);
        }

        return \in_array($response->status(), [200, 201], true);
    }

    /**
     * Whether the dataset currently exists on the RAG server (GET 200 vs 404).
     */
    public function datasetExists(string $datasetId): bool
    {
        $url = "{$this->apiUrl}/datasets/{$datasetId}";

        $response = $this->request()->get($url);

        if ($response->status() >= 500) {
            throw RagIngestionRequestException::forFailedResponse('GET', $url, $response->status(), $response->body());
        }

        return 200 === $response->status();
    }

    /**
     * @inheritDoc
     */
    public function ingest(TextIngestionPayload $payload, string $idempotencyKey): string
    {
        $url = "{$this->apiUrl}/integrations/text-ingestions";

        $response = $this->request()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post($url, [
                'dataset_id' => $payload->datasetId,
                'external_document_id' => $payload->externalDocumentId,
                'text' => $payload->text,
                'content_format' => $payload->contentFormat,
                'display_name' => $payload->displayName,
                'metadata' => $payload->metadata,
            ]);

        if ($response->status() >= 500) {
            throw RagIngestionRequestException::forFailedResponse('POST', $url, $response->status(), $response->body());
        }

        if (!$response->successful()) {
            throw RagIngestionRequestException::forFailedResponse('POST', $url, $response->status(), $response->body());
        }

        $taskId = (string)($response->json('task_id') ?? '');

        if ('' === $taskId) {
            throw RagIngestionRequestException::forMissingTaskId('POST', $url);
        }

        return $taskId;
    }

    /**
     * @inheritDoc
     */
    public function ingestFile(FileIngestionPayload $payload, string $idempotencyKey, ?string $existingDocumentId = null): FileIngestionResult
    {
        $replacing = null !== $existingDocumentId && '' !== $existingDocumentId;

        $url = $replacing
            ? "{$this->apiUrl}/documents/{$existingDocumentId}"
            : "{$this->apiUrl}/documents";

        $form = [
            'display_name' => $payload->displayName,
            'metadata_json' => \json_encode($payload->metadata, JSON_THROW_ON_ERROR),
        ];

        if (!$replacing) {
            // A replacement stays in the document's dataset; dataset_id is
            // only part of the create request.
            $form['dataset_id'] = $payload->datasetId;
        }

        $response = $this->request()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->attach(
                'file',
                $payload->content,
                $payload->filename,
                ['Content-Type' => $payload->mimeType],
            )
            ->post($url, $form);

        if ($response->status() >= 500) {
            throw RagIngestionRequestException::forFailedResponse('POST', $url, $response->status(), $response->body());
        }

        if (!$response->successful()) {
            throw RagIngestionRequestException::forFailedResponse('POST', $url, $response->status(), $response->body());
        }

        // 202 = accepted, 200 = idempotent replay or unchanged replacement
        // ("file not newer"); an unchanged replacement may carry no fresh
        // pipeline reference, hence the document's latest task as fallback.
        $taskId = (string)($response->json('pipeline.task_id')
            ?? $response->json('document.latest_task_id')
            ?? '');

        if ('' === $taskId) {
            throw RagIngestionRequestException::forMissingTaskId('POST', $url);
        }

        $documentId = (string)($response->json('document.document_id')
            ?? $response->json('document.id')
            ?? '');

        if ('' === $documentId) {
            throw RagIngestionRequestException::forMissingDocumentId('POST', $url);
        }

        return new FileIngestionResult($taskId, $documentId);
    }

    /**
     * @inheritDoc
     */
    public function checkIngestion(string $handle): RagIngestionCheck
    {
        $status = \strtolower(\trim($this->fetchTaskStatus($handle)));

        if (\in_array($status, self::TERMINAL_SUCCESS_STATUSES, true)) {
            return RagIngestionCheck::succeeded();
        }

        if (\in_array($status, self::TERMINAL_FAILURE_STATUSES, true)) {
            return RagIngestionCheck::failed(
                \sprintf('RAG task "%s" ended with status "%s".', $handle, $status),
            );
        }

        return RagIngestionCheck::running();
    }

    /**
     * @inheritDoc
     */
    public function deleteDocument(string $datasetId, string $externalDocumentId): bool
    {
        // Unified document endpoint: keys by the managed document id
        // (`adoc_*`) or the legacy indexed uuid alone — no dataset scope.
        $url = "{$this->apiUrl}/documents/{$externalDocumentId}";

        $response = $this->request()->delete($url);

        return $response->successful();
    }

    /**
     * Raw status string of a Temporal ingestion task (e.g. "running",
     * "succeeded", "failed").
     */
    private function fetchTaskStatus(string $taskId): string
    {
        $url = "{$this->apiUrl}/pipeline/tasks/{$taskId}";

        $response = $this->request()->get($url);

        if (!$response->successful()) {
            throw RagIngestionRequestException::forFailedResponse('GET', $url, $response->status(), $response->body());
        }

        // The task endpoint wraps the task in a "task" object
        // ({"success":true,"task":{"status":...}}); the bare key is kept as
        // a fallback for unwrapped variants.
        return (string)($response->json('task.status') ?? $response->json('status') ?? '');
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout);
    }
}
