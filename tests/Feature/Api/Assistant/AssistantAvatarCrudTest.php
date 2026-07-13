<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantAvatarCrudTest extends TestCase
{
    use RefreshDatabase;
    private const DOTS_PATTERN = 'background-color: #E5E5F7; opacity: 0.8; background: radial-gradient(#444CF7 15%, transparent 16%) 0 0, radial-gradient(#444CF7 15%, transparent 16%) 5px 5px, radial-gradient(#444CF733 15%, transparent 20%) 0 1px, radial-gradient(#444CF733 15%, transparent 20%) 5px 6px; background-size: 10px 10px;';

    public function testOwnerCanCreateAvatarForTheirAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])
            ->assertCreated();

        $this->assertDatabaseHas('assistant_avatars', [
            'assistant_id' => $assistant->id,
            'name' => '🧠',
        ]);
    }

    public function testOwnerCanCreateAvatarWithEmptyStrings(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '', 'icon_css' => ''],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])
            ->assertCreated();

        $this->assertDatabaseHas('assistant_avatars', [
            'assistant_id' => $assistant->id,
            'name' => '',
            'icon_css' => '',
        ]);
    }

    public function testNonOwnerCannotCreateAvatar(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();
    }

    public function testNonOwnerCannotCreateAvatarForOrganizationalAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();
    }

    public function testSharedUserCannotCreateAvatar(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $shared = User::factory()->create();
        $assistant->sharedUsers()->sync([$shared->id]);
        $this->actingAsUser($shared);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();
    }

    public function testCreateRequiresAssistantRelationship(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
            ],
        ])->assertStatus(422);
    }

    public function testAssistantCanHaveOnlyOneAvatar(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);
        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(422);
    }

    public function testIndexIsScopedToVisibleAssistants(): void
    {
        $ownerA = User::factory()->create();
        $publicAssistant = Assistant::factory()->create([
            'creator_id' => $ownerA->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $publicAvatar = AssistantAvatar::factory()->create(['assistant_id' => $publicAssistant->id]);

        $ownerB = User::factory()->create();
        $privateAssistant = Assistant::factory()->create([
            'creator_id' => $ownerB->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $privateAvatar = AssistantAvatar::factory()->create(['assistant_id' => $privateAssistant->id]);

        $this->actingAsUser(User::factory()->create());

        $ids = collect($this->jsonApiRaw('get', '/api/hawki/v1/assistant-avatars')->assertOk()->json('data'))
            ->pluck('id');

        self::assertContains((string) $publicAvatar->id, $ids);
        self::assertNotContains((string) $privateAvatar->id, $ids);
    }

    public function testShowIsGatedByAssistantVisibility(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-avatars/{$avatar->id}")->assertOk();

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-avatars/{$avatar->id}")->assertForbidden();
    }

    public function testUpdateAndDeleteAreOwnerOnly(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-avatars/{$avatar->id}", [
            'data' => [
                'type' => 'assistant-avatars',
                'id' => (string) $avatar->id,
                'attributes' => ['name' => '💡'],
            ],
        ])->assertForbidden();

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-avatars/{$avatar->id}")->assertForbidden();

        $this->actingAsUser($owner);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-avatars/{$avatar->id}", [
            'data' => [
                'type' => 'assistant-avatars',
                'id' => (string) $avatar->id,
                'attributes' => ['name' => '💡'],
            ],
        ])->assertOk();

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-avatars/{$avatar->id}")->assertNoContent();
    }

    public function testOwnerCanUpdateAvatarWithEmptyStrings(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);
        $this->actingAsUser($owner);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-avatars/{$avatar->id}", [
            'data' => [
                'type' => 'assistant-avatars',
                'id' => (string) $avatar->id,
                'attributes' => ['name' => '', 'icon_css' => ''],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('assistant_avatars', [
            'id' => $avatar->id,
            'name' => '',
            'icon_css' => '',
        ]);
    }

    public function testAvatarAssistantLinkDoesNotLeakPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-avatars/{$avatar->id}/assistant")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-avatars/{$avatar->id}/assistant")
            ->assertForbidden();
    }
}
