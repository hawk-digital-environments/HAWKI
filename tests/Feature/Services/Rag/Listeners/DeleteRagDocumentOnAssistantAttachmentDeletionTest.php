<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Rag\Listeners;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Listeners\DeleteRagDocumentOnAssistantAttachmentDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DeleteRagDocumentOnAssistantAttachmentDeletion::class)]
class DeleteRagDocumentOnAssistantAttachmentDeletionTest extends TestCase
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
        static::assertInstanceOf(DeleteRagDocumentOnAssistantAttachmentDeletion::class, $this->app->make(DeleteRagDocumentOnAssistantAttachmentDeletion::class));
    }

    public function testItDeletesTheDatasetDocumentWhenAnIngestedAttachmentIsRemoved(): void
    {
        $attachment = $this->createAttachment('ingested', 'rag-delete-1');

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('deleteDocument')
            ->once()
            ->with('assistant_' . $this->assistant->id, 'rag-delete-1')
            ->andReturn(true);

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItPrefersTheStoredRagDocumentHandleOverTheUuid(): void
    {
        $attachment = $this->createAttachment('ingested', 'rag-delete-handle', 'adoc_123');

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('deleteDocument')
            ->once()
            ->with('assistant_' . $this->assistant->id, 'adoc_123')
            ->andReturn(true);

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItPassesTheStoredSourceHandleOfTextIngestions(): void
    {
        $sourceId = 'source_' . str_repeat('ab', 16);
        $attachment = $this->createAttachment('ingested', 'rag-delete-text', $sourceId);

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('deleteDocument')
            ->once()
            ->with('assistant_' . $this->assistant->id, $sourceId)
            ->andReturn(true);

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItOnlyLogsAWarningWhenDeIngestionFails(): void
    {
        $attachment = $this->createAttachment('ingested');

        Log::shouldReceive('warning')->once();

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('deleteDocument')
            ->once()
            ->andThrow(new \RuntimeException('rag server down'));

        // The attachment deletion itself must survive a failing RAG call.
        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItSkipsDeIngestionForNeverIngestedAttachments(): void
    {
        $attachment = $this->createAttachment(null);

        $this->mock(RagIngesterInterface::class)
            ->shouldNotReceive('deleteDocument');

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItSkipsDeIngestionWhenRagIsDisabled(): void
    {
        config(['rag.enabled' => false]);

        $attachment = $this->createAttachment('ingested');

        $this->mock(RagIngesterInterface::class)
            ->shouldNotReceive('deleteDocument');

        $attachment->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $attachment->id]);
    }

    public function testItCascadesDeIngestionWhenTheAssistantIsDeleted(): void
    {
        $ingested = $this->createAttachment('ingested');
        $skipped = $this->createAttachment('skipped');

        $this->mock(RagIngesterInterface::class)
            ->shouldReceive('deleteDocument')
            ->once()
            ->with('assistant_' . $this->assistant->id, $ingested->uuid)
            ->andReturn(true);

        $this->assistant->delete();

        $this->assertDatabaseMissing('assistant_attachments', ['id' => $ingested->id]);
        $this->assertDatabaseMissing('assistant_attachments', ['id' => $skipped->id]);
    }

    private function createAttachment(?string $ragStatus, ?string $uuid = null, ?string $ragDocumentId = null): AssistantAttachment
    {
        $attachment = $this->assistant->assistantAttachments()->create([
            'uuid' => $uuid ?? 'rag-delete-' . uniqid(),
            'name' => 'knowledge.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $this->assistant->creator_id,
        ]);

        $forceFill = ['rag_status' => $ragStatus];

        if (null !== $ragDocumentId) {
            $forceFill['rag_document_id'] = $ragDocumentId;
        }

        if (null !== $ragStatus || null !== $ragDocumentId) {
            // rag_* state transitions are repository-owned, hence forceFill.
            $attachment->forceFill(array_filter($forceFill, static fn ($value): bool => null !== $value))->save();
        }

        return $attachment->refresh();
    }
}
