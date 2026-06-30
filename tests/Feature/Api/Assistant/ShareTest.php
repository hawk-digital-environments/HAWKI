<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_attach_shared_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/shared-users", [
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

    public function test_owner_can_detach_shared_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $assistant->sharedUsers()->sync([$alice->id, $bob->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/relationships/shared-users", [
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

    public function test_owner_can_list_shared_users(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}/relationships/shared-users")
            ->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((string) $alice->id, $ids);
    }

    public function test_owner_can_replace_shared_users(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $assistant->sharedUsers()->sync([$alice->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}/relationships/shared-users", [
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

    public function test_attach_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $alice = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])->assertSuccessful();

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $alice->id]],
        ])->assertSuccessful();

        $this->assertEquals(1, $assistant->sharedUsers()->count());
    }

    public function test_non_owner_cannot_attach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $intruder = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($intruder);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_non_owner_cannot_detach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $intruder = User::factory()->create();
        $target = User::factory()->create();
        $assistant->sharedUsers()->sync([$target->id]);

        Sanctum::actingAs($intruder);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertForbidden();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_non_owner_cannot_list_shared_users(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->sharedUsers()->sync([User::factory()->create()->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}/relationships/shared-users")
            ->assertForbidden();
    }

    public function test_guest_cannot_attach(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $target = User::factory()->create();

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $target->id]],
        ])->assertUnauthorized();
    }

    public function test_attach_requires_data_array(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        // to-many relationship expects data as an array; a single object is a
        // structural error (400), not a validation error.
        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => ['type' => 'users', 'id' => '1'],
        ])->assertStatus(400);
    }

    public function test_shared_user_can_list_private_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        Sanctum::actingAs($viewer);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function test_shared_user_can_show_private_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        Sanctum::actingAs($viewer);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);
    }

    public function test_non_shared_user_cannot_list_private_assistant(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_shared_user_cannot_show_private_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function test_shared_user_can_list_draft_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        Sanctum::actingAs($viewer);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function test_shared_user_can_show_draft_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        Sanctum::actingAs($viewer);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);
    }

    public function test_detaching_revokes_visibility(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/relationships/shared-users", [
            'data' => [['type' => 'users', 'id' => (string) $viewer->id]],
        ])->assertSuccessful();

        Sanctum::actingAs($viewer);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertForbidden();
    }
}
