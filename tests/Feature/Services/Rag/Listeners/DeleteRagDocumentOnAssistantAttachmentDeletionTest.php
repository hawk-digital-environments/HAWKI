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

    private function createAttachment(?string $ragStatus, ?string $uuid = null): AssistantAttachment
    {
        $attachment = $this->assistant->assistantAttachments()->create([
            'uuid' => $uuid ?? 'rag-delete-' . uniqid(),
            'name' => 'knowledge.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $this->assistant->creator_id,
        ]);

        if (null !== $ragStatus) {
            // rag_* state transitions are repository-owned, hence forceFill.
            $attachment->forceFill(['rag_status' => $ragStatus])->save();
        }

        return $attachment->refresh();
    }
}
