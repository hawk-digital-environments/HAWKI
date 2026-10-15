<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Listeners\UserRemovalListener;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Users\Events\UserRemovedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserRemovalListener::class)]
#[CoversClass(AssistantRepository::class)]
class UserRemovalListenerTest extends TestCase
{
    use RefreshDatabase;

    public function testItDeletesDraftAndPrivateAssistants(): void
    {
        $user = User::factory()->create();

        $draft = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::DRAFT,
        ]);
        $private = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE,
        ]);
        // A private assistant with a pending publication request: the current
        // stage decides, so it is deleted along with its review request.
        $pendingRelease = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE,
            'requested_release_stage' => AssistantReleaseStage::ORGANIZATIONAL,
        ]);
        $pendingRelease->assistantReview()->create();

        $this->fireListener($user);

        static::assertDatabaseMissing('assistants', ['id' => $draft->id]);
        static::assertDatabaseMissing('assistants', ['id' => $private->id]);
        static::assertDatabaseMissing('assistants', ['id' => $pendingRelease->id]);
        static::assertDatabaseMissing('assistant_reviews', ['assistant_id' => $pendingRelease->id]);
    }

    public function testItKeepsPublicAssistants(): void
    {
        $user = User::factory()->create();

        $organizational = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL,
        ]);
        $federated = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::FEDERATED,
        ]);

        $this->fireListener($user);

        static::assertDatabaseHas('assistants', ['id' => $organizational->id, 'release_stage' => 'organizational']);
        static::assertDatabaseHas('assistants', ['id' => $federated->id, 'release_stage' => 'federated']);
        static::assertDatabaseHas('users', ['id' => $user->id, 'isRemoved' => 0]);
    }

    public function testItCascadesAttachmentsAndSharesOfDeletedPrivateAssistants(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $private = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE,
        ]);
        $private->sharedUsers()->attach($other->id);
        $private->assistantAttachments()->create([
            'uuid' => 'user-removal-listener-test-uuid',
            'name' => 'knowledge.pdf',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $user->id,
        ]);

        $this->fireListener($user);

        static::assertDatabaseMissing('assistants', ['id' => $private->id]);
        static::assertDatabaseMissing('assistant_attachments', ['assistant_id' => $private->id]);
        static::assertDatabaseMissing('assistant_shared_users', ['assistant_id' => $private->id, 'user_id' => $other->id]);
    }

    public function testItDetachesFavoritesAndReceivedSharesOnForeignAssistants(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Assistants owned by someone else that the removed user favored/was shared on.
        $favorited = Assistant::factory()->create(['creator_id' => $other->id]);
        $shared = Assistant::factory()->create(['creator_id' => $other->id]);
        $user->favoriteAssistants()->attach($favorited->id);
        $shared->sharedUsers()->attach($user->id);

        $this->fireListener($user);

        static::assertDatabaseMissing('assistant_favorite_users', ['user_id' => $user->id]);
        static::assertDatabaseMissing('assistant_shared_users', ['user_id' => $user->id]);
        static::assertDatabaseHas('assistants', ['id' => $favorited->id]);
        static::assertDatabaseHas('assistants', ['id' => $shared->id]);
    }

    private function fireListener(User $user): void
    {
        $listener = new UserRemovalListener($this->app->make(AssistantRepository::class));
        $listener->handle(new UserRemovedEvent($user));
    }
}
