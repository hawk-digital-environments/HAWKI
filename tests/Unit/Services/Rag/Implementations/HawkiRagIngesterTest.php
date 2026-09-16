<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Rag\Implementations;

use App\Services\Rag\Exceptions\RagIngestionRequestException;
use App\Services\Rag\Implementations\HawkiRagIngester;
use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\RagIngestionOutcome;
use App\Services\Rag\Values\TextIngestionPayload;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HawkiRagIngester::class)]
class HawkiRagIngesterTest extends TestCase
{
    private const string API_URL = 'http://rag.test/api';

    private HawkiRagIngester $sut;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rag.api_url' => self::API_URL,
            'rag.api_key' => 'test-key',
            'rag.timeout' => 7,
        ]);

        $this->sut = new HawkiRagIngester(self::API_URL, 'test-key', 7);
    }

    public function testItConstructs(): void
    {
        static::assertInstanceOf(HawkiRagIngester::class, $this->sut);
    }

    public function testItUsesAnExistingDatasetWithoutCreatingIt(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => true], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['success' => true], 200),
        ]);

        static::assertTrue($this->sut->ensureDataset('assistant_1'));

        Http::assertSent(static fn ($request): bool => 'GET' === $request->method());
        // The dataset is never (re-)created; the only POST is the self-grant.
        Http::assertNotSent(static fn ($request): bool => 'POST' === $request->method()
            && $request->url() === self::API_URL . '/datasets');
    }

    public function testItCreatesAMissingDatasetAndRedeemsTheGrantToken(): void
    {
        $grantToken = 'dgt_' . str_repeat('ab', 32);

        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => false], 404),
            self::API_URL . '/datasets' => Http::response(['dataset_id' => 'assistant_1', 'grant_token' => $grantToken], 201),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['success' => true, 'replayed' => false], 200),
        ]);

        static::assertTrue($this->sut->ensureDataset('assistant_1'));

        Http::assertSent(static function ($request) use ($grantToken): bool {
            $body = json_decode((string)$request->body(), true);

            return 'POST' === $request->method()
                && ['Bearer test-key'] === $request->header('Authorization')
                && 'assistant_1' === ($body['dataset_id'] ?? null);
        });

        // The one-time creation token is redeemed with the self-grant call.
        Http::assertSent(static function ($request) use ($grantToken): bool {
            $body = json_decode((string)$request->body(), true);

            return 'POST' === $request->method()
                && $request->url() === self::API_URL . '/datasets/assistant_1/ingest-grants/self'
                && ['Bearer test-key'] === $request->header('Authorization')
                && ['grant_token' => $grantToken] === $body;
        });
    }

    public function testItReplaysSelfGrantsWithoutATokenOnExistingDatasets(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => true], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['success' => true, 'replayed' => true], 200),
        ]);

        static::assertTrue($this->sut->ensureDataset('assistant_1'));

        Http::assertSent(static fn ($request): bool => 'POST' === $request->method()
            && $request->url() === self::API_URL . '/datasets/assistant_1/ingest-grants/self'
            && !\str_contains((string)$request->body(), 'grant_token'));
    }

    public function testItReturnsFalseWhenACompatibleReplayCannotSelfGrant(): void
    {
        // Dataset pre-created by someone else: compatible replay returns the
        // dataset but never re-issues the creator's one-time token.
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => false], 404),
            self::API_URL . '/datasets' => Http::response(['dataset_id' => 'assistant_1'], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(json_encode(['error' => 'grant_token_required']), 403),
        ]);

        static::assertFalse($this->sut->ensureDataset('assistant_1'));
    }

    public function testItAcceptsACompatibleDuplicateDatasetOnCreation(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => false], 404),
            self::API_URL . '/datasets' => Http::response(['dataset_id' => 'assistant_1'], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['success' => true], 200),
        ]);

        static::assertTrue($this->sut->ensureDataset('assistant_1'));
    }

    public function testItResolvesACreateRaceByReCheckingExistence(): void
    {
        // GET 404, POST 409 (created meanwhile), re-GET 200, self-grant 200.
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::sequence()
                ->push(['success' => false], 404)
                ->push(['success' => true], 200),
            self::API_URL . '/datasets' => Http::response(['message' => 'conflict'], 409),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['success' => true], 200),
        ]);

        static::assertTrue($this->sut->ensureDataset('assistant_1'));
    }

    public function testItRejectsAPermanentDatasetConflict(): void
    {
        // GET 404, POST 409, re-GET still 404 -> the conflict is permanent.
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::sequence()
                ->push(['success' => false], 404)
                ->push(['success' => false], 404),
            self::API_URL . '/datasets' => Http::response(['message' => 'conflict'], 409),
        ]);

        static::assertFalse($this->sut->ensureDataset('assistant_1'));
    }

    public function testItRejectsForbiddenDatasetCreation(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => false], 404),
            self::API_URL . '/datasets' => Http::response(['message' => 'no grant'], 404),
        ]);

        static::assertFalse($this->sut->ensureDataset('assistant_1'));
    }

    public function testItReturnsFalseWhenTheIngestSelfGrantIsRejected(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => true], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(json_encode(['message' => 'dataset_not_found']), 404),
        ]);

        static::assertFalse($this->sut->ensureDataset('assistant_1'));
    }

    public function testItTreatsAThrottledIngestSelfGrantAsTransient(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => true], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['message' => 'Too Many Attempts.'], 429),
        ]);

        try {
            $this->sut->ensureDataset('assistant_1');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertTrue($e->isTransient());
            static::assertStringContainsString('429', $e->getMessage());
        }
    }

    public function testItTreatsAServerErrorOnTheIngestSelfGrantAsTransient(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['success' => true], 200),
            self::API_URL . '/datasets/assistant_1/ingest-grants/self' => Http::response(['message' => 'boom'], 502),
        ]);

        try {
            $this->sut->ensureDataset('assistant_1');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertTrue($e->isTransient());
        }
    }

    public function testItThrowsTransientExceptionOnServerErrorsForDataset(): void
    {
        Http::fake([
            self::API_URL . '/datasets/assistant_1' => Http::response(['message' => 'boom'], 502),
        ]);

        try {
            $this->sut->ensureDataset('assistant_1');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertTrue($e->isTransient());
            static::assertStringContainsString('502', $e->getMessage());
        }
    }

    public function testItIngestsTextAndReturnsTaskAndSourceHandle(): void
    {
        Http::fake([
            self::API_URL . '/integrations/text-ingestions' => Http::response([
                'task_id' => 'task-1',
                'source_id' => 'source_' . str_repeat('a', 32),
                'status' => 'running',
                'replayed' => false,
            ], 202),
        ]);

        $payload = new TextIngestionPayload(
            datasetId: 'assistant_1',
            externalDocumentId: 'file-uuid',
            text: '# Knowledge',
            displayName: 'knowledge.md',
            metadata: ['assistant_id' => 1],
        );

        $result = $this->sut->ingest($payload, 'attachment-file-uuid-etag');

        static::assertSame('task-1', $result->taskId);
        static::assertSame('source_' . str_repeat('a', 32), $result->sourceId);

        Http::assertSent(static function ($request): bool {
            return 'POST' === $request->method()
                && ['Bearer test-key'] === $request->header('Authorization')
                && ['attachment-file-uuid-etag'] === $request->header('Idempotency-Key')
                && \str_contains((string)$request->body(), '"dataset_id":"assistant_1"')
                && \str_contains((string)$request->body(), '"external_document_id":"file-uuid"')
                && \str_contains((string)$request->body(), '"content_format":"markdown"');
        });
    }

    public function testItThrowsOnValidationErrorForIngestion(): void
    {
        Http::fake([
            self::API_URL . '/integrations/text-ingestions' => Http::response(json_encode(['message' => 'Invalid']), 422),
        ]);

        $payload = new TextIngestionPayload('assistant_1', 'file-uuid', 'text', 'doc.md');

        try {
            $this->sut->ingest($payload, 'key');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertFalse($e->isTransient());
        }
    }

    public function testItThrowsWhenTheTaskHandleIsMissing(): void
    {
        Http::fake([
            self::API_URL . '/integrations/text-ingestions' => Http::response(['status' => 'running'], 202),
        ]);

        $payload = new TextIngestionPayload('assistant_1', 'file-uuid', 'text', 'doc.md');

        try {
            $this->sut->ingest($payload, 'key');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertStringContainsString('task_id', $e->getMessage());
        }
    }

    public function testItThrowsWhenTheSourceHandleIsMissing(): void
    {
        Http::fake([
            self::API_URL . '/integrations/text-ingestions' => Http::response(['task_id' => 'task-1'], 202),
        ]);

        $payload = new TextIngestionPayload('assistant_1', 'file-uuid', 'text', 'doc.md');

        try {
            $this->sut->ingest($payload, 'key');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertStringContainsString('source_id', $e->getMessage());
        }
    }

    public function testItUploadsAFileAndReturnsTaskAndDocumentHandle(): void
    {
        Http::fake([
            self::API_URL . '/documents' => Http::response([
                'success' => true,
                'document' => ['document_id' => 'adoc_1'],
                'pipeline' => ['task_id' => 'task-1', 'job_id' => 'job-1'],
            ], 202),
        ]);

        $result = $this->sut->ingestFile($this->filePayload(), 'attachment-file-uuid-etag');

        static::assertSame('task-1', $result->taskId);
        static::assertSame('adoc_1', $result->documentId);

        Http::assertSent(static function ($request): bool {
            $body = (string)$request->body();

            return 'POST' === $request->method()
                && ['Bearer test-key'] === $request->header('Authorization')
                && ['attachment-file-uuid-etag'] === $request->header('Idempotency-Key')
                && \str_contains($body, 'name="dataset_id"')
                && \str_contains($body, 'assistant_1')
                && \str_contains($body, 'name="display_name"')
                && \str_contains($body, 'knowledge.pdf')
                && \str_contains($body, 'attachment_uuid')
                && \str_contains($body, 'Content-Type: application/pdf');
        });
    }

    public function testItReplacesAnExistingDocumentViaItsHandle(): void
    {
        Http::fake([
            self::API_URL . '/documents/adoc_1' => Http::response([
                'success' => true,
                'document' => ['document_id' => 'adoc_1'],
                'pipeline' => ['task_id' => 'task-2'],
            ], 202),
        ]);

        $result = $this->sut->ingestFile($this->filePayload(), 'attachment-file-uuid-etag2', 'adoc_1');

        static::assertSame('task-2', $result->taskId);
        static::assertSame('adoc_1', $result->documentId);

        Http::assertSent(static function ($request): bool {
            $body = (string)$request->body();

            return 'POST' === $request->method()
                && \str_contains($request->url(), '/documents/adoc_1')
                // A replacement stays in the document's dataset: no dataset_id.
                && !\str_contains($body, 'name="dataset_id"');
        });
    }

    public function testItFallsBackToTheDocumentsLatestTaskOnUnchangedReplacements(): void
    {
        Http::fake([
            self::API_URL . '/documents/adoc_1' => Http::response([
                'success' => true,
                'operation' => ['type' => 'replace', 'status' => 'skipped', 'reason' => 'unchanged'],
                'document' => ['document_id' => 'adoc_1', 'latest_task_id' => 'task-prev'],
            ], 200),
        ]);

        $result = $this->sut->ingestFile($this->filePayload(), 'attachment-file-uuid-etag2', 'adoc_1');

        static::assertSame('task-prev', $result->taskId);
        static::assertSame('adoc_1', $result->documentId);
    }

    public function testItThrowsOnValidationErrorForFileIngestion(): void
    {
        Http::fake([
            self::API_URL . '/documents' => Http::response(json_encode(['message' => 'Invalid']), 422),
        ]);

        try {
            $this->sut->ingestFile($this->filePayload(), 'key');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertFalse($e->isTransient());
        }
    }

    public function testItThrowsWhenTheFileTaskHandleIsMissing(): void
    {
        Http::fake([
            self::API_URL . '/documents' => Http::response([
                'success' => true,
                'document' => ['document_id' => 'adoc_1'],
            ], 202),
        ]);

        try {
            $this->sut->ingestFile($this->filePayload(), 'key');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertStringContainsString('task_id', $e->getMessage());
        }
    }

    public function testItThrowsWhenTheDocumentHandleIsMissing(): void
    {
        Http::fake([
            self::API_URL . '/documents' => Http::response([
                'success' => true,
                'pipeline' => ['task_id' => 'task-1'],
            ], 202),
        ]);

        try {
            $this->sut->ingestFile($this->filePayload(), 'key');
            static::fail('Expected RagIngestionRequestException was not thrown.');
        } catch (RagIngestionRequestException $e) {
            static::assertStringContainsString('document_id', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: RagIngestionOutcome}>
     */
    public static function provideTaskStatusMappingData(): iterable
    {
        yield 'succeeded' => ['succeeded', RagIngestionOutcome::SUCCEEDED];
        yield 'completed' => ['completed', RagIngestionOutcome::SUCCEEDED];
        yield 'done' => ['done', RagIngestionOutcome::SUCCEEDED];
        yield 'SUCCEEDED (case/whitespace)' => ['  SUCCEEDED  ', RagIngestionOutcome::SUCCEEDED];
        yield 'running' => ['running', RagIngestionOutcome::RUNNING];
        yield 'pending' => ['pending', RagIngestionOutcome::RUNNING];
        yield 'unknown stays running' => ['weird-status', RagIngestionOutcome::RUNNING];
        yield 'failed' => ['failed', RagIngestionOutcome::FAILED];
        yield 'error' => ['error', RagIngestionOutcome::FAILED];
        yield 'cancelled' => ['cancelled', RagIngestionOutcome::FAILED];
    }

    /**
     * @param string $status raw task status the RAG server reports
     * @param RagIngestionOutcome $expected mapped outcome in HAWKI's vocabulary
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideTaskStatusMappingData')]
    public function testItMapsTaskStatusesToOutcomes(string $status, RagIngestionOutcome $expected): void
    {
        Http::fake([
            self::API_URL . '/pipeline/tasks/task-1' => Http::response(['task_id' => 'task-1', 'status' => $status]),
        ]);

        $check = $this->sut->checkIngestion('task-1');

        static::assertSame($expected, $check->outcome);

        if (RagIngestionOutcome::FAILED === $expected) {
            static::assertStringContainsString($status, (string)$check->reason);
        }
    }

    public function testItFetchingAnUnknownTaskThrows(): void
    {
        Http::fake([
            self::API_URL . '/pipeline/tasks/task-1' => Http::response(['message' => 'not found'], 404),
        ]);

        $this->expectException(RagIngestionRequestException::class);

        $this->sut->checkIngestion('task-1');
    }

    public function testItDeletesDocuments(): void
    {
        Http::fake([
            self::API_URL . '/documents/adoc_1' => Http::response([], 204),
        ]);

        static::assertTrue($this->sut->deleteDocument('assistant_1', 'adoc_1'));
    }

    public function testItReportsRejectedDocumentDeletionAsFalse(): void
    {
        Http::fake([
            self::API_URL . '/documents/file-uuid' => Http::response(json_encode(['message' => 'gone']), 404),
        ]);

        static::assertFalse($this->sut->deleteDocument('assistant_1', 'file-uuid'));
    }

    public function testItDeletesTextIngestionsThroughTheirSourceHandle(): void
    {
        $sourceId = 'source_' . str_repeat('ab', 16);

        Http::fake([
            self::API_URL . "/integrations/text-ingestions/{$sourceId}" => Http::response([
                'source_id' => $sourceId,
                'dataset_id' => 'assistant_1',
                'status' => 'deleted',
                'deleted' => true,
                'replayed' => false,
            ], 200),
        ]);

        static::assertTrue($this->sut->deleteDocument('assistant_1', $sourceId));

        Http::assertSent(static function ($request) use ($sourceId): bool {
            return 'DELETE' === $request->method()
                && \str_ends_with($request->url(), "/integrations/text-ingestions/{$sourceId}")
                && ['Bearer test-key'] === $request->header('Authorization')
                // Underscores in dataset/source ids are outside the server's
                // Idempotency-Key charset, so the key is a hash instead.
                && !\str_contains((string)($request->header('Idempotency-Key')[0] ?? ''), '_')
                && 'delete-' === \substr((string)($request->header('Idempotency-Key')[0] ?? ''), 0, 7);
        });
    }

    public function testItRetriesABusyTextIngestionUntilItSucceeds(): void
    {
        $sourceId = 'source_' . str_repeat('ab', 16);

        Http::fake([
            self::API_URL . "/integrations/text-ingestions/{$sourceId}" => Http::sequence()
                ->push(['error' => 'text_ingestion_source_busy'], 409)
                ->push(['error' => 'text_ingestion_source_busy'], 409)
                ->push(['source_id' => $sourceId, 'status' => 'deleted', 'deleted' => true], 200),
        ]);

        static::assertTrue($this->sut->deleteDocument('assistant_1', $sourceId));

        Http::assertSentCount(3);
    }

    public function testItReportsRejectedTextIngestionDeletionAsFalse(): void
    {
        $sourceId = 'source_' . str_repeat('cd', 16);

        Http::fake([
            self::API_URL . "/integrations/text-ingestions/{$sourceId}" => Http::response(json_encode(['error' => 'text_ingestion_source_busy']), 409),
        ]);

        static::assertFalse($this->sut->deleteDocument('assistant_1', $sourceId));

        // Retryable refusals exhaust all attempts before giving up.
        Http::assertSentCount(3);
    }

    public function testItDoesNotRetryPermanentTextIngestionDeletionRejections(): void
    {
        $sourceId = 'source_' . str_repeat('ef', 16);

        Http::fake([
            self::API_URL . "/integrations/text-ingestions/{$sourceId}" => Http::response(json_encode(['error' => 'text_ingestion_not_found']), 404),
        ]);

        static::assertFalse($this->sut->deleteDocument('assistant_1', $sourceId));

        Http::assertSentCount(1);
    }

    public function testItRetriesServerErrorsOnDocumentDeletion(): void
    {
        Http::fake([
            self::API_URL . '/documents/adoc_1' => Http::sequence()
                ->push(['message' => 'boom'], 502)
                ->push([], 204),
        ]);

        static::assertTrue($this->sut->deleteDocument('assistant_1', 'adoc_1'));

        Http::assertSentCount(2);
    }

    private function filePayload(): FileIngestionPayload
    {
        return new FileIngestionPayload(
            datasetId: 'assistant_1',
            externalDocumentId: 'file-uuid',
            filename: 'knowledge.pdf',
            mimeType: 'application/pdf',
            content: '%PDF-1.4 binary content',
            displayName: 'knowledge.pdf',
            metadata: ['assistant_id' => 1, 'attachment_uuid' => 'file-uuid'],
        );
    }
}
