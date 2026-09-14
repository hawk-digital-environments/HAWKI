<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Rag\Listeners;

use App\Jobs\IngestAttachmentToRag;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Listeners\CancelPendingRagIngestionOnAssistantAttachmentDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CancelPendingRagIngestionOnAssistantAttachmentDeletion::class)]
class CancelPendingRagIngestionOnAssistantAttachmentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Assistant $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        $this->assistant = Assistant::factory()->create();
    }

    public function testItConstructs(): void
    {
        static::assertInstanceOf(
            CancelPendingRagIngestionOnAssistantAttachmentDeletion::class,
            $this->app->make(CancelPendingRagIngestionOnAssistantAttachmentDeletion::class),
        );
    }

    public function testItCancelsTheBatchOfAPendingAttachment(): void
    {
        Queue::fake();
        // The de-ingestion sibling listener must not attempt a real RAG call.
        $this->mock(RagIngesterInterface::class)->shouldReceive('deleteDocument')->andReturn(true);

        $attachment = $this->createAttachment('pending');
        $attachment->forceFill(['rag_batch_id' => $this->dispatchBatchFor($attachment->id)])->save();

        $attachment->refresh()->delete();

        $batch = Bus::findBatch($attachment->rag_batch_id);
        static::assertNotNull($batch);
        static::assertTrue($batch->cancelled());

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItCancelsEvenWhenRagIsDisabled(): void
    {
        config(['rag.enabled' => false]);
        Queue::fake();

        $attachment = $this->createAttachment('pending');
        $attachment->forceFill(['rag_batch_id' => $this->dispatchBatchFor($attachment->id)])->save();

        $attachment->refresh()->delete();

        $batch = Bus::findBatch($attachment->rag_batch_id);
        static::assertNotNull($batch);
        static::assertTrue($batch->cancelled());
    }

    public function testItIsANoOpForAttachmentsWithoutABatch(): void
    {
        $this->mock(RagIngesterInterface::class)->shouldReceive('deleteDocument')->andReturn(true);

        $attachment = $this->createAttachment('pending');

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItSurvivesAStaleBatchId(): void
    {
        $attachment = $this->createAttachment(null, 'batch-id-without-a-row');

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    private function dispatchBatchFor(int $attachmentId): string
    {
        $batch = Bus::batch([new IngestAttachmentToRag($this->assistant->id, $attachmentId)])
            ->name('rag-ingestion-test')
            ->dispatch();

        return $batch->id;
    }

    private function createAttachment(?string $ragStatus, ?string $ragBatchId = null): AssistantAttachment
    {
        $attachment = $this->assistant->assistantAttachments()->create([
            'uuid' => 'rag-cancel-' . uniqid(),
            'name' => 'knowledge.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $this->assistant->creator_id,
        ]);

        $forceFill = [];

        if (null !== $ragStatus) {
            $forceFill['rag_status'] = $ragStatus;
        }

        if (null !== $ragBatchId) {
            $forceFill['rag_batch_id'] = $ragBatchId;
        }

        if ([] !== $forceFill) {
            // rag_* state transitions are repository-owned, hence forceFill.
            $attachment->forceFill($forceFill)->save();
        }

        return $attachment->refresh();
    }
}
