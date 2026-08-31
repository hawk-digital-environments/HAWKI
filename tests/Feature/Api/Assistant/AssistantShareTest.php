<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantShareTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnerCanAttachSharedUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id],
                ['type' => 'users', 'id' => (string) $bob->id],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

    public function testOwnerCanDetachSharedUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $assistant->sharedUsers()->sync([$alice->id, $bob->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [
                ['type' => 'users', 'id' => (string) $alice->id],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

    public function testOwnerCanListSharedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users")
            ->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id');
        self::assertContains((string) $alice->id, $ids);
    }

    public function testOwnerCanReplaceSharedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [
                ['type' => 'users', 'id' => (string) $bob->id],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $alice->id,
        ]);
        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $bob->id,
        ]);
    }

    public function testAttachIsIdempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])->assertSuccessful();

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])->assertSuccessful();

        self::assertEquals(1, $assistant->sharedUsers()->count());
    }

    public function testNonOwnerCannotAttach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $intruder = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAsUser($intruder);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
    }

    public function testNonOwnerCannotDetach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $intruder = User::factory()->create();
        $target = User::factory()->create();
        $assistant->sharedUsers()->sync([$target->id]);

        $this->actingAsUser($intruder);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertForbidden();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
    }

    public function testNonOwnerCannotListSharedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users")
            ->assertForbidden();
    }

    public function testGuestCannotAttach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $target = User::factory()->create();

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertStatus(401);
    }

    public function testAttachRequiresDataArray(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        // to-many relationship expects data as an array; a single object is a
        // structural error (400), not a validation error.
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => ['type' => 'users', 'id' => '1'],
        ])->assertStatus(400);
    }

    public function testSharedUserCanListPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function testSharedUserCanShowPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);
    }

    public function testNonSharedUserCannotListPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $other = User::factory()->create();

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testNonSharedUserCannotShowPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $other = User::factory()->create();

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function testSharedUserCanListDraftAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function testSharedUserCanShowDraftAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);
    }

    public function testDetachingRevokesVisibility(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $viewer->id]],
        ])->assertSuccessful();

        $this->actingAsUser($viewer);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }
}
