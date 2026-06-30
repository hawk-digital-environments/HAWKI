<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
class AssistantOwnedChildrenWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_create_user_prompt_attaches_to_assistant(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant();
        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'Hello'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        // The created prompt is linked and readable through the assistant relationship.
        $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_user_prompts")
            ->assertOk()
            ->assertJsonCount(1, 'included');

        $this->assertDatabaseHas('user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'Hello',
        ]);
    }

    public function test_create_feedback_attaches_to_assistant(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => ReleaseStage::ORGANIZATIONAL->value]);
        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Nice'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_feedback")
            ->assertOk()
            ->assertJsonCount(1, 'included');

        // Author is the authenticated caller, not a client-supplied value.
        $this->assertDatabaseHas('feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'Nice',
        ]);
    }

    public function test_create_setting_value_attaches_to_assistant(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant();
        $setting = $this->assistantSetting();
        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-setting-values', [
            'data' => [
                'type' => 'assistant-setting-values',
                'attributes' => ['value' => 'professional'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'setting' => ['data' => ['type' => 'assistant-settings', 'id' => (string) $setting->id]],
                ],
            ],
        ])->assertCreated();

        $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_setting_values")
            ->assertOk()
            ->assertJsonCount(1, 'included');

        $this->assertDatabaseHas('assistant_setting_values', [
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
        ]);
    }

    public function test_create_records_version_in_workflow(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => ReleaseStage::ORGANIZATIONAL->value]);
        Sanctum::actingAs($owner);

        $initial = $assistant->versions()->count();

        $this->jsonApi('post', '/api/assistant-user-prompts', [
            'data' => [
                'type' => 'assistant-user-prompts',
                'attributes' => ['text' => 'one'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertSame($initial, $assistant->fresh()->versions()->count());
        $this->assertEquals(['user_prompts'], $assistant->versions()->latest('version')->first()->changed_keys);
    }

    public function test_delete_records_version_in_workflow(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => ReleaseStage::ORGANIZATIONAL->value]);
        // Seed via Eloquent (no AssistantUpdated event); the delete then merges into
        // the latest version within the debounce window.
        $prompt = $assistant->user_prompts()->create(['text' => 'bye']);
        $initial = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('delete', "/api/assistant-user-prompts/{$prompt->id}")->assertNoContent();

        $this->assertSame($initial, $assistant->fresh()->versions()->count());
        $this->assertEquals(['user_prompts'], $assistant->versions()->latest('version')->first()->changed_keys);
        $this->assertDatabaseMissing('user_prompts', ['id' => $prompt->id]);
    }

    public function test_setting_values_workflow_debounces_versions(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => ReleaseStage::ORGANIZATIONAL->value]);
        $formality = AssistantSetting::factory()->create(['key' => 'formality', 'ui_options' => [['value' => 'casual', 'label' => 'Casual'], ['value' => 'professional', 'label' => 'Professional']]]);
        $language = AssistantSetting::factory()->create(['key' => 'language', 'ui_options' => [['value' => 'en', 'label' => 'English'], ['value' => 'de', 'label' => 'German']]]);
        Sanctum::actingAs($owner);

        $initial = $assistant->versions()->count();

        // The frontend saves two settings as two separate requests; they merge into one version.
        $this->createSettingValue($assistant, $formality, 'professional');
        $this->createSettingValue($assistant, $language, 'de');

        $this->assertSame($initial, $assistant->fresh()->versions()->count());
        $this->assertSame(2, $assistant->fresh()->settingValues()->count());
    }

    public function test_non_owner_cannot_attach_owned_children(): void
    {
        [$owner, $assistant] = $this->ownerAndAssistant(['release_stage' => ReleaseStage::ORGANIZATIONAL->value]);
        $setting = $this->assistantSetting();

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', '/api/assistant-user-prompts', $this->childPayload('assistant-user-prompts', $assistant, ['text' => 'x']))
            ->assertForbidden();

        $this->jsonApi('post', '/api/assistant-setting-values', [
            'data' => [
                'type' => 'assistant-setting-values',
                'attributes' => ['value' => 'professional'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'setting' => ['data' => ['type' => 'assistant-settings', 'id' => (string) $setting->id]],
                ],
            ],
        ])->assertForbidden();
    }

    private function createSettingValue(Assistant $assistant, AssistantSetting $setting, mixed $value): void
    {
        $this->jsonApi('post', '/api/assistant-setting-values', [
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
