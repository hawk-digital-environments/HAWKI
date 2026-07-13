<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantUserPrompt;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantUserPromptsTest extends TestCase
{
    use RefreshDatabase;

    public function testOwnerCanCreateUserPrompt(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'First prompt'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.attributes.text', 'First prompt');
        self::assertNotNull($response->json('data.id'));

        $this->assertDatabaseHas('assistant_user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'First prompt',
        ]);
    }

    public function testOwnerCanDeleteUserPrompt(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $prompt = $assistant->assistantUserPrompts()->create(['text' => 'Delete me']);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-user-prompts/{$prompt->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('assistant_user_prompts', [
            'id' => $prompt->id,
        ]);
    }

    public function testNonOwnerCannotDelete(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $prompt = $assistant->assistantUserPrompts()->create(['text' => 'Keep me']);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-user-prompts/{$prompt->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('assistant_user_prompts', ['id' => $prompt->id]);
    }

    public function testNonOwnerCannotCreate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Nope'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();

        self::assertSame(0, AssistantUserPrompt::where('assistant_id', $assistant->id)->count());
    }

    public function testGuestCannotCreate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Nope'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(401);
    }

    public function testCreateRequiresText(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => (object) [],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(422);
    }

    public function testCreateRequiresAssistantLinkage(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'No parent'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('assistant_user_prompts', ['assistant_id' => $assistant->id]);
    }

    public function testCreateRequiresCorrectType(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistants',
                'attributes' => ['text' => 'Wrong type'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(409);
    }

    public function testCreateRecordsVersionWhenOrganizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Prompt one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertEquals(['assistant_user_prompts'], $version->changed_keys);
        self::assertSame('{"changes":["assistant_user_prompts"]}', $version->text);
    }

    public function testCreateSkipsVersionWhenPrivate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Prompt one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
    }

    public function testCreateSkipsVersionWhenDraft(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Prompt one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
    }

    public function testDeleteRecordsVersionWhenOrganizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $prompt = $assistant->assistantUserPrompts()->create(['text' => 'Bye']);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-user-prompts/{$prompt->id}")
            ->assertNoContent();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
        self::assertEquals(['assistant_user_prompts'], $assistant->assistantVersions()->latest('version')->first()->changed_keys);
    }
}
