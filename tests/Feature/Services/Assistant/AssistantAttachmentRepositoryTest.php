<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantAttachmentRepository::class)]
class AssistantAttachmentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function testFindsTheNewestAttachmentForARagDocumentId(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::create(['name' => 'Test Assistant', 'handle' => 'test-assistant', 'model' => 'gpt-4.1', 'user_id' => $user->id]);

        $older = $assistant->assistantAttachments()->create([
            'uuid' => 'uuid-older',
            'name' => 'old.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $user->id,
        ]);
        $newer = $assistant->assistantAttachments()->create([
            'uuid' => 'uuid-newer',
            'name' => 'new.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $user->id,
        ]);
        $other = $assistant->assistantAttachments()->create([
            'uuid' => 'uuid-other',
            'name' => 'other.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $user->id,
        ]);

        $older->forceFill(['rag_document_id' => 'adoc_shared'])->save();
        $newer->forceFill(['rag_document_id' => 'adoc_shared'])->save();
        $other->forceFill(['rag_document_id' => 'adoc_different'])->save();

        $repository = $this->app->make(AssistantAttachmentRepository::class);

        static::assertTrue($newer->is($repository->findOneByRagDocumentId('adoc_shared')));
        static::assertFalse($older->is($repository->findOneByRagDocumentId('adoc_shared')));
        static::assertTrue($other->is($repository->findOneByRagDocumentId('adoc_different')));
        static::assertNull($repository->findOneByRagDocumentId('adoc_unknown'));
    }
}
