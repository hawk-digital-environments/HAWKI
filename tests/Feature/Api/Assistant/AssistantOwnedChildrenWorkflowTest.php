<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * End-to-end "create + attach" workflow for assistant-owned children.
 *
 * These resources are created via their standalone endpoints with the parent
 * supplied in relationships.assistant (JSON:API create-with-relationship).
 * The workflow verifies that a created child is actually linked to the parent,
 * readable through the parent's relationship, and that versioning side-effects
 * fire across the lifecycle.
 */
#[CoversNothing()]
class AssistantOwnedChildrenWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateUserPromptAttachesToAssistant(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant();
        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Hello'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        // The created prompt is linked and readable through the assistant relationship.
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_user_prompts")
            ->assertOk()
            ->assertJsonCount(1, 'included');

        $this->assertDatabaseHas('assistant_user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'Hello',
        ]);
    }

    public function testCreateFeedbackAttachesToAssistant(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value]);
        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Nice'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_feedback")
            ->assertOk()
            ->assertJsonCount(1, 'included');

        // Author is the authenticated caller, not a client-supplied value.
        $this->assertDatabaseHas('assistant_feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'Nice',
        ]);
    }

    public function testCreateSettingValueAttachesToAssistant(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant();
        $setting = $this->assistantSetting();
        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', [
            'data' => [
                'type' => 'assistant-setting-values',
                'attributes' => ['value' => 'professional'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'setting' => ['data' => ['type' => 'assistant-settings', 'id' => (string) $setting->id]],
                ],
            ],
        ])->assertCreated();

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_setting_values")
            ->assertOk()
            ->assertJsonCount(1, 'included');

        $this->assertDatabaseHas('assistant_setting_values', [
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
        ]);
    }

    public function testCreateRecordsVersionInWorkflow(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value]);
        $this->actingAsUser($owner);

        $initial = $assistant->assistantVersions()->count();

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        self::assertSame($initial, $assistant->fresh()->assistantVersions()->count());
        self::assertEquals(['assistant_user_prompts'], $assistant->assistantVersions()->latest('version')->first()->changed_keys);
    }

    public function testDeleteRecordsVersionInWorkflow(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value]);
        // Seed via Eloquent (no AssistantUpdated event); the delete then merges into
        // the latest version within the debounce window.
        $prompt = $assistant->assistantUserPrompts()->create(['text' => 'bye']);
        $initial = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistant-user-prompts/{$prompt->id}")->assertNoContent();

        self::assertSame($initial, $assistant->fresh()->assistantVersions()->count());
        self::assertEquals(['assistant_user_prompts'], $assistant->assistantVersions()->latest('version')->first()->changed_keys);
        $this->assertDatabaseMissing('assistant_user_prompts', ['id' => $prompt->id]);
    }

    public function testSettingValuesWorkflowDebouncesVersions(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value]);
        $formality = AssistantSetting::factory()->create(['key' => 'formality', 'ui_options' => [['value' => 'casual', 'label' => 'Casual'], ['value' => 'professional', 'label' => 'Professional']]]);
        $language = AssistantSetting::factory()->create(['key' => 'language', 'ui_options' => [['value' => 'en', 'label' => 'English'], ['value' => 'de', 'label' => 'German']]]);
        $this->actingAsUser($owner);

        $initial = $assistant->assistantVersions()->count();

        // The frontend saves two settings as two separate requests; they merge into one version.
        $this->createSettingValue($assistant, $formality, 'professional');
        $this->createSettingValue($assistant, $language, 'de');

        self::assertSame($initial, $assistant->fresh()->assistantVersions()->count());
        self::assertSame(2, $assistant->fresh()->settingValues()->count());
    }

    public function testNonOwnerCannotAttachOwnedChildren(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value]);
        $setting = $this->assistantSetting();

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-user-prompts', $this->childPayload('assistant-user-prompts', $assistant, ['text' => 'x']))
            ->assertForbidden();

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', [
            'data' => [
                'type' => 'assistant-setting-values',
                'attributes' => ['value' => 'professional'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'setting' => ['data' => ['type' => 'assistant-settings', 'id' => (string) $setting->id]],
                ],
            ],
        ])->assertForbidden();

        self::assertSame(0, $assistant->assistantUserPrompts()->count());
        self::assertSame(0, $assistant->settingValues()->count());
    }

    private function assistantSetting(): AssistantSetting
    {
        return AssistantSetting::factory()->create([
            'key' => 'formality',
            'ui_options' => [
                ['value' => 'casual', 'label' => 'Casual'],
                ['value' => 'balanced', 'label' => 'Balanced'],
                ['value' => 'professional', 'label' => 'Professional'],
            ],
        ]);
    }

    private function createSettingValue(Assistant $assistant, AssistantSetting $setting, mixed $value): void
    {
        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', [
            'data' => [
                'type' => 'assistant-setting-values',
                'attributes' => ['value' => $value],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'setting' => ['data' => ['type' => 'assistant-settings', 'id' => (string) $setting->id]],
                ],
            ],
        ])->assertCreated();
    }

    private function childPayload(string $type, Assistant $assistant, array $attributes): array
    {
        return [
            'data' => [
                'type' => $type,
                'attributes' => $attributes,
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ];
    }

    /**
     * @return array{0: User, 1: Assistant}
     */
    private function ownerAndAssistant(array $assistantOverrides = []): array
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(array_merge(
            ['creator_id' => $owner->id],
            $assistantOverrides,
        ));

        return [$owner, $assistant];
    }
}
