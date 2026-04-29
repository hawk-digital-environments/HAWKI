<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\UserPrompt;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantUserPromptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_user_prompt(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'First prompt'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.attributes.text', 'First prompt');
        $this->assertNotNull($response->json('data.id'));

        $this->assertDatabaseHas('user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'First prompt',
        ]);
    }

    public function test_owner_can_delete_user_prompt(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $prompt = $assistant->user_prompts()->create(['text' => 'Delete me']);

        Sanctum::actingAs($owner);

        $this->jsonApi('delete', "/api/assistant-user-prompts/{$prompt->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('user_prompts', [
            'id' => $prompt->id,
        ]);
    }

    public function test_non_owner_cannot_delete(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $prompt = $assistant->user_prompts()->create(['text' => 'Keep me']);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('delete', "/api/assistant-user-prompts/{$prompt->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('user_prompts', ['id' => $prompt->id]);
    }

    public function test_non_owner_cannot_create(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Nope'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();

        $this->assertSame(0, UserPrompt::where('assistant_id', $assistant->id)->count());
    }

    public function test_guest_cannot_create(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Nope'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertUnauthorized();
    }

    public function test_create_requires_text(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => (object) [],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_create_requires_assistant_linkage(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'No parent'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('user_prompts', ['assistant_id' => $assistant->id]);
    }

    public function test_create_requires_correct_type(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistants',
                'attributes' => ['text' => 'Wrong type'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(409);
    }

    public function test_create_records_version_when_organizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Prompt one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());

        $version = $assistant->versions()->latest('version')->first();
        $this->assertEquals(['user_prompts'], $version->changed_keys);
        $this->assertSame('{"changes":["user_prompts"]}', $version->text);
    }

    public function test_create_skips_version_when_private(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Prompt one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());
    }

    public function test_create_skips_version_when_draft(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::DRAFT->value,
        ]);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Prompt one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());
    }

    public function test_delete_records_version_when_organizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $prompt = $assistant->user_prompts()->create(['text' => 'Bye']);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('delete', "/api/assistant-user-prompts/{$prompt->id}")
            ->assertNoContent();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());
        $this->assertEquals(['user_prompts'], $assistant->versions()->latest('version')->first()->changed_keys);
    }
}
