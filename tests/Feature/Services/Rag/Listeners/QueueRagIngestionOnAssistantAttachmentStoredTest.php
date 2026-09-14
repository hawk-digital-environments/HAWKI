<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Rag\Listeners;

use App\Jobs\IngestAttachmentToRag;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Models\User;
use App\Services\Assistant\Events\AssistantAttachmentStoredEvent;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Listeners\QueueRagIngestionOnAssistantAttachmentStored;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(QueueRagIngestionOnAssistantAttachmentStored::class)]
class QueueRagIngestionOnAssistantAttachmentStoredTest extends TestCase
{
    use RefreshDatabase;

    public function testItConstructs(): void
    {
        static::assertInstanceOf(QueueRagIngestionOnAssistantAttachmentStored::class, $this->app->make(QueueRagIngestionOnAssistantAttachmentStored::class));
    }

    public function testItProvisionsTheDatasetAndDispatchesJobWhenEnabled(): void
    {
        config(['rag.enabled' => true]);
        Queue::fake();
        $assistant = $this->assistantWithAttachment('rag-listen-1');

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('ensureDataset')
            ->once()
            ->with('assistant_' . $assistant->id)
            ->andReturn(true);

        AssistantAttachmentStoredEvent::dispatch($assistant, $assistant->assistantAttachments()->first(), User::factory()->create());

        Queue::assertPushed(IngestAttachmentToRag::class, static function (IngestAttachmentToRag $job) use ($assistant): bool {
            $attachment = $assistant->assistantAttachments()->first();

            return $job->assistantId === $assistant->id && $job->assistantAttachmentId === $attachment->id;
        });

        $attachment = $assistant->assistantAttachments()->first();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $attachment->id,
            'rag_status' => 'pending',
        ]);

        // The job is revocable: its batch id is persisted and refers to
        // the real batch the job was dispatched in.
        $batchId = $attachment->refresh()->rag_batch_id;

        static::assertNotNull($batchId);
        $this->assertDatabaseHas('job_batches', [
            'id' => $batchId,
            'name' => \sprintf('rag-ingestion-assistant-%d-attachment-%d', $assistant->id, $attachment->id),
        ]);
    }

    public function testItStillDispatchesWhenPreFlightProvisioningFails(): void
    {
        config(['rag.enabled' => true]);
        Queue::fake();
        Log::shouldReceive('warning')->once();
        $assistant = $this->assistantWithAttachment('rag-listen-2');

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('ensureDataset')
            ->once()
            ->andThrow(new \RuntimeException('rag server down'));

        AssistantAttachmentStoredEvent::dispatch($assistant, $assistant->assistantAttachments()->first(), User::factory()->create());

        // Best-effort: the upload is never blocked, the job retries provisioning.
        Queue::assertPushed(IngestAttachmentToRag::class);

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $assistant->assistantAttachments()->first()->id,
            'rag_status' => 'pending',
        ]);
    }

    public function testItWarnsButContinuesWhenProvisioningIsRejected(): void
    {
        config(['rag.enabled' => true]);
        Queue::fake();
        Log::shouldReceive('warning')->once();
        $assistant = $this->assistantWithAttachment('rag-listen-3');

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('ensureDataset')
            ->once()
            ->andReturn(false);

        AssistantAttachmentStoredEvent::dispatch($assistant, $assistant->assistantAttachments()->first(), User::factory()->create());

        Queue::assertPushed(IngestAttachmentToRag::class);

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $assistant->assistantAttachments()->first()->id,
            'rag_status' => 'pending',
        ]);
    }

    public function testItIsANoOpWhenRagIngestionIsDisabled(): void
    {
        config(['rag.enabled' => false]);
        Queue::fake();
        $assistant = $this->assistantWithAttachment('rag-listen-4');

        $this->mock(RagIngesterInterface::class)
            ->shouldNotReceive('ensureDataset');

        AssistantAttachmentStoredEvent::dispatch($assistant, $assistant->assistantAttachments()->first(), User::factory()->create());

        Queue::assertNothingPushed();

        $this->assertDatabaseHas('assistant_attachments', [
            'id' => $assistant->assistantAttachments()->first()->id,
            'rag_status' => null,
        ]);
    }

    public function testItDispatchesThroughTheUploadFlowWhenEnabled(): void
    {
        // End-to-end upload path: the event fires from the service and the
        // listener picks it up via auto-discovery.
        config(['rag.enabled' => true]);
        Queue::fake();

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('ensureDataset')
            ->andReturn(true);

        $assistant = Assistant::factory()->create(['release_stage' => 'organizational']);
        $this->stubFileStorageForUpload();

        $this->actingAsUser(User::find($assistant->creator_id));

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            ['file' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10)],
            ['Accept' => 'application/vnd.api+json'],
        )->assertSuccessful();

        Queue::assertPushed(IngestAttachmentToRag::class);

        $this->assertDatabaseHas('assistant_attachments', [
            'rag_status' => 'pending',
        ]);
    }

    /**
     * @return array{0: Assistant, 1: AssistantAttachment}
     */
    private function assistantWithAttachment(string $uuid): Assistant
    {
        $assistant = Assistant::factory()->create();
        $assistant->assistantAttachments()->create([
            'uuid' => $uuid,
            'name' => 'knowledge.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $assistant->creator_id,
        ]);

        return $assistant->refresh();
    }

    /**
     * Short-circuit the on-disk storage pipeline so the upload action
     * persists a real attachment row without touching the disk.
     */
    private function stubFileStorageForUpload(): void
    {
        $storedFile = self::createStub(\App\Services\Storage\Values\StoredFile::class);
        $storedFile->method('getUuid')->willReturn('rag-upload-' . uniqid());
        $storedFile->method('getOriginalFilename')->willReturn('doc.pdf');
        $storedFile->method('getCategory')->willReturn(\App\Services\Storage\Values\StoredFileCategory::ASSISTANT);
        $storedFile->method('getMimeType')->willReturn('application/pdf');
        $storedFile->method('getFileType')->willReturn(\App\Services\Storage\Values\FileType::PDF);

        $this->mock(\App\Services\Storage\FileStorageService::class, static function ($mock) use ($storedFile): void {
            $mock->shouldReceive('getAllowedMimeTypes')->andReturn(['application/pdf']);
            $mock->shouldReceive('getMaxFileSize')->andReturn(10 * 1024 * 1024);
            $mock->shouldReceive('store')->andReturn($storedFile);
        });
    }
}
