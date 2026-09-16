<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Rag;

use App\Jobs\IngestAttachmentToRag;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Agents\Utils\ExtractTextCollector;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Values\FileIngestionPayload;
use App\Services\Rag\Values\FileIngestionResult;
use App\Services\Rag\Values\RagIngestionCheck;
use App\Services\Rag\Values\TextIngestionPayload;
use App\Services\Rag\Values\TextIngestionResult;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFile;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IngestAttachmentToRag::class)]
class IngestAttachmentToRagJobTest extends TestCase
{
    use RefreshDatabase;

    private Assistant $assistant;

    private AssistantAttachment $attachment;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        $this->assistant = Assistant::factory()->create();
        $this->attachment = $this->assistant->assistantAttachments()->create([
            'uuid' => 'rag-job-uuid',
            'name' => 'knowledge.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $this->assistant->creator_id,
        ]);
        // rag_* state transitions are repository-owned, hence forceFill.
        $this->attachment->forceFill(['rag_status' => 'pending'])->save();
    }

    public function testItConstructs(): void
    {
        $job = new IngestAttachmentToRag(1, 2);

        // Batch cancellation must discard a queued run before execution.
        static::assertEquals([new SkipIfBatchCancelled()], $job->middleware());
    }

    public function testItIgnoresAVanishedAttachment(): void
    {
        $this->runJob(999999);

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'pending',
        ]);
    }

    public function testItIgnoresAnAlreadyResolvedAttachment(): void
    {
        $this->attachment->forceFill(['rag_status' => 'ingested'])->save();

        $this->runJob();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'ingested',
        ]);
    }

    public function testItSkipsWhenTheStoredFileIsGone(): void
    {
        $this->mock(FileStorageService::class)
            ->shouldReceive('retrieve')
            ->andReturn(null);

        $this->runJob();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'skipped',
        ]);
    }

    public function testItSkipsWhenTheFileHasNoExtractableText(): void
    {
        $this->mockStorageAndCollector('');

        $this->runJob();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'skipped',
        ]);
    }

    public function testItFailsWhenTheDatasetIsRejected(): void
    {
        $this->mockStorageAndCollector('# Knowledge');
        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('ensureDataset')
            ->with('assistant_' . $this->assistant->id)
            ->andReturn(false);

        $this->runJob();

        $attachment = $this->attachment->refresh();

        static::assertSame('failed', $attachment->rag_status->value);
        static::assertStringContainsString('refused to provide dataset', $attachment->rag_error);
    }

    public function testItIngestsAndMarksIngestedWhenTheTaskSucceeds(): void
    {
        $this->mockStorageAndCollector('# Knowledge');
        $assistantId = $this->assistant->id;
        $sourceId = 'source_' . str_repeat('ab', 16);

        $this->mock(RagIngesterInterface::class, function ($mock) use ($assistantId, $sourceId): void {
            $mock->shouldReceive('ensureDataset')->andReturn(true);
            $mock->shouldReceive('ingest')->once()->withArgs(
                static function (TextIngestionPayload $payload, string $idempotencyKey) use ($assistantId): bool {
                    return "assistant_{$assistantId}" === $payload->datasetId
                        && 'rag-job-uuid' === $payload->externalDocumentId
                        && '# Knowledge' === $payload->text
                        && 'knowledge.pdf' === $payload->displayName
                        && \str_starts_with($idempotencyKey, 'attachment-rag-job-uuid-');
                },
            )->andReturn(new TextIngestionResult('task-1', $sourceId));
            $mock->shouldReceive('checkIngestion')->with('task-1')->andReturn(RagIngestionCheck::succeeded());
        });

        $this->runJob();

        $attachment = $this->attachment->refresh();

        static::assertSame('ingested', $attachment->rag_status->value);
        static::assertSame('task-1', $attachment->rag_task_id);
        // The source handle routes later deletions to the text-ingestion endpoint.
        static::assertSame($sourceId, $attachment->rag_document_id);
        static::assertNotNull($attachment->rag_ingested_at);
    }

    public function testItFailsWhenTheRagTaskReportsFailure(): void
    {
        $this->mockStorageAndCollector('# Knowledge');

        $this->mock(RagIngesterInterface::class, function ($mock): void {
            $mock->shouldReceive('ensureDataset')->andReturn(true);
            $mock->shouldReceive('ingest')->andReturn(new TextIngestionResult('task-2', 'source_' . str_repeat('cd', 16)));
            $mock->shouldReceive('checkIngestion')->with('task-2')->andReturn(RagIngestionCheck::failed('RAG task "task-2" ended with status "failed".'));
        });

        $this->runJob();

        $attachment = $this->attachment->refresh();

        static::assertSame('failed', $attachment->rag_status->value);
        static::assertStringContainsString('task-2', (string)$attachment->rag_error);
    }

    public function testItPollsARunningTaskOnTheNextRun(): void
    {
        // First run: task accepted but still running -> status stays
        // "ingesting" (the sync test driver does not re-run released jobs).
        $this->mockStorageAndCollector('# Knowledge');

        $this->mock(RagIngesterInterface::class, function ($mock): void {
            $mock->shouldReceive('ensureDataset')->andReturn(true);
            $mock->shouldReceive('ingest')->andReturn(new TextIngestionResult('task-3', 'source_' . str_repeat('ef', 16)));
            $mock->shouldReceive('checkIngestion')->with('task-3')->andReturn(RagIngestionCheck::running());
        });

        $this->runJob();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'ingesting',
            'rag_task_id' => 'task-3',
        ]);

        // Second run (as the real worker would after the release delay):
        // the task now reports success.
        $this->mock(RagIngesterInterface::class, function ($mock): void {
            $mock->shouldReceive('checkIngestion')->with('task-3')->andReturn(RagIngestionCheck::succeeded());
        });

        $this->runJob();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'ingested',
        ]);
    }

    public function testItUploadsTheOriginalFileInFileModeWithoutExtractingText(): void
    {
        config(['rag.attachment_ingestion' => 'file']);
        $this->mockStorageForFileUpload();
        $this->mock(ExtractTextCollector::class)->shouldNotReceive('collect');
        $assistantId = $this->assistant->id;

        $this->mock(RagIngesterInterface::class, function ($mock) use ($assistantId): void {
            $mock->shouldReceive('ensureDataset')->andReturn(true);
            $mock->shouldReceive('ingest')->never();
            $mock->shouldReceive('ingestFile')->once()->withArgs(
                static function (FileIngestionPayload $payload, string $idempotencyKey, ?string $existingDocumentId) use ($assistantId): bool {
                    return "assistant_{$assistantId}" === $payload->datasetId
                        && 'rag-job-uuid' === $payload->externalDocumentId
                        && 'knowledge.pdf' === $payload->filename
                        && 'application/pdf' === $payload->mimeType
                        && '%PDF-1.4 content' === $payload->content
                        && 'knowledge.pdf' === $payload->displayName
                        && null === $existingDocumentId
                        && \str_starts_with($idempotencyKey, 'attachment-rag-job-uuid-');
                },
            )->andReturn(new FileIngestionResult('task-4', 'adoc_4'));
            $mock->shouldReceive('checkIngestion')->with('task-4')->andReturn(RagIngestionCheck::succeeded());
        });

        $this->runJob();

        $attachment = $this->attachment->refresh();

        static::assertSame('ingested', $attachment->rag_status->value);
        static::assertSame('task-4', $attachment->rag_task_id);
        static::assertSame('adoc_4', $attachment->rag_document_id);
        static::assertNotNull($attachment->rag_ingested_at);
    }

    public function testItReplacesTheStoredDocumentOnReIngestionInFileMode(): void
    {
        config(['rag.attachment_ingestion' => 'file']);
        $this->attachment->forceFill(['rag_document_id' => 'adoc_old'])->save();
        $this->mockStorageForFileUpload();

        $this->mock(RagIngesterInterface::class, function ($mock): void {
            $mock->shouldReceive('ensureDataset')->andReturn(true);
            $mock->shouldReceive('ingestFile')->once()->withArgs(
                static fn (FileIngestionPayload $payload, string $idempotencyKey, ?string $existingDocumentId): bool => 'adoc_old' === $existingDocumentId,
            )->andReturn(new FileIngestionResult('task-5', 'adoc_old'));
            $mock->shouldReceive('checkIngestion')->with('task-5')->andReturn(RagIngestionCheck::succeeded());
        });

        $this->runJob();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $this->attachment->id,
            'rag_status' => 'ingested',
            'rag_task_id' => 'task-5',
            'rag_document_id' => 'adoc_old',
        ]);
    }

    public function testItMarksTheAttachmentFailedWhenTheJobExpires(): void
    {
        (new IngestAttachmentToRag($this->assistant->id, $this->attachment->id))->failed(
            new \RuntimeException('job timed out'),
        );

        $attachment = $this->attachment->refresh();

        static::assertSame('failed', $attachment->rag_status->value);
        static::assertStringContainsString('job timed out', $attachment->rag_error);
    }

    /**
     * Runs the job synchronously through the bus dispatcher. PendingDispatch
     * relies on destructor timing, which PHPUnit keeps alive past the
     * assertions; dispatchSync executes the handler inline instead.
     */
    private function runJob(?int $attachmentId = null): void
    {
        $this->app->make(BusDispatcher::class)
            ->dispatchSync(new IngestAttachmentToRag($this->assistant->id, $attachmentId ?? $this->attachment->id));
    }

    /**
     * Binds a storage stub returning a file with the given ETag plus an
     * extract collector returning the given text, and returns the collector
     * mock so tests can add call expectations.
     */
    private function mockStorageAndCollector(string $text): \Mockery\MockInterface
    {
        $storedFile = self::createStub(StoredFile::class);
        $storedFile->method('getUuid')->willReturn('rag-job-uuid');
        $storedFile->method('getEtag')->willReturn('etag-1');

        $this->mock(FileStorageService::class)
            ->shouldReceive('retrieve')
            ->andReturn($storedFile);

        return $this->mock(ExtractTextCollector::class)
            ->shouldReceive('collect')
            ->withArgs(static fn (StoredFile $file): bool => 'rag-job-uuid' === $file->getUuid())
            ->andReturn($text)
            ->getMock();
    }

    /**
     * Binds a storage stub returning a file with content, name, and mime
     * for file-mode uploads.
     */
    private function mockStorageForFileUpload(): void
    {
        $storedFile = self::createStub(StoredFile::class);
        $storedFile->method('getUuid')->willReturn('rag-job-uuid');
        $storedFile->method('getEtag')->willReturn('etag-1');
        $storedFile->method('getOriginalFilename')->willReturn('knowledge.pdf');
        $storedFile->method('getMimeType')->willReturn('application/pdf');
        $storedFile->method('getContent')->willReturn('%PDF-1.4 content');

        $this->mock(FileStorageService::class)
            ->shouldReceive('retrieve')
            ->andReturn($storedFile);
    }
}
