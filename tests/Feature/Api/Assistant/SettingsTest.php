<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private AssistantSetting $formality;

    private AssistantSetting $language;

    private AssistantSetting $answerLength;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formality = AssistantSetting::factory()->create([
            'key' => 'formality',
            'ui_options' => [
                ['value' => '', 'label' => 'Not set'],
                ['value' => 'casual', 'label' => 'Casual'],
                ['value' => 'balanced', 'label' => 'Balanced'],
                ['value' => 'professional', 'label' => 'Professional'],
                ['value' => 'academic', 'label' => 'Academic'],
            ],
        ]);

        $this->language = AssistantSetting::factory()->create([
            'key' => 'language',
            'ui_options' => [
                ['value' => '', 'label' => 'Not set'],
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'de', 'label' => 'German'],
            ],
        ]);

        $this->answerLength = AssistantSetting::factory()->create([
            'key' => 'answer_length',
            'ui_options' => [
                ['value' => '', 'label' => 'Not set'],
                ['value' => 'concise', 'label' => 'Concise'],
                ['value' => 'balanced', 'label' => 'Balanced'],
                ['value' => 'detailed', 'label' => 'Detailed'],
            ],
        ]);
    }

    private function storePayload(AssistantSetting $setting, mixed $value, Assistant $assistant): array
    {
        return [
            'data' => [
                'type' => 'assistant-setting-values',
                'attributes' => ['value' => $value],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'setting' => ['data' => ['type' => 'assistant-settings', 'id' => (string) $setting->id]],
                ],
            ],
        ];
    }

    public function test_owner_can_create_setting_value(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_setting_values', [
            'assistant_id' => $assistant->id,
            'setting_id' => $this->formality->id,
            'value' => json_encode('professional'),
        ]);
    }

    public function test_create_rejects_duplicate_setting_per_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertStatus(422);

        $this->assertSame(1, $assistant->settingValues()->where('setting_id', $this->formality->id)->count());
    }

    public function test_create_rejects_invalid_value(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'telepathic', $assistant));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/value');
    }

    public function test_owner_can_update_value(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $value = $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('patch', "/api/assistant-setting-values/{$value->id}", [
            'data' => [
                'type' => 'assistant-setting-values',
                'id' => (string) $value->id,
                'attributes' => ['value' => 'professional'],
            ],
        ])->assertOk();

        $this->assertSame('professional', $assistant->fresh()->settingValues->firstWhere('setting_id', $this->formality->id)->value);
    }

    public function test_update_rejects_invalid_value(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $value = $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('patch', "/api/assistant-setting-values/{$value->id}", [
            'data' => [
                'type' => 'assistant-setting-values',
                'id' => (string) $value->id,
                'attributes' => ['value' => 'telepathic'],
            ],
        ])->assertStatus(422);
    }

    public function test_non_owner_cannot_create(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertForbidden();
    }

    public function test_guest_cannot_create(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertUnauthorized();
    }

    public function test_setting_change_records_version_when_organizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());

        $version = $assistant->versions()->latest('version')->first();
        $this->assertEquals(['setting_values'], $version->changed_keys);
        $this->assertSame('{"changes":["setting_values"]}', $version->text);
    }

    public function test_setting_change_skips_version_when_private(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());
    }

    public function test_rapid_setting_changes_are_debounced_into_one_version(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->versions()->count();

        Sanctum::actingAs($owner);

        // Two rapid setting-value creates within the debounce window merge into one version.
        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->language, 'de', $assistant))
            ->assertCreated();

        $this->assertSame($initialVersionCount, $assistant->fresh()->versions()->count());
        $this->assertSame(2, $assistant->fresh()->settingValues()->count());
    }

    public function test_owner_can_store_empty_value_for_empty_tolerant_setting(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($this->formality, '', $assistant))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_setting_values', [
            'assistant_id' => $assistant->id,
            'setting_id' => $this->formality->id,
            'value' => json_encode(''),
        ]);
    }

    public function test_owner_can_update_to_empty_value(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $value = $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('patch', "/api/assistant-setting-values/{$value->id}", [
            'data' => [
                'type' => 'assistant-setting-values',
                'id' => (string) $value->id,
                'attributes' => ['value' => ''],
            ],
        ])->assertOk();

        $this->assertSame('', $assistant->fresh()->settingValues->firstWhere('setting_id', $this->formality->id)->value);
    }

    public function test_empty_value_rejected_for_setting_without_empty_option(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $setting = AssistantSetting::factory()->create([
            'ui_options' => [
                ['value' => 'only', 'label' => 'Only'],
                ['value' => 'valid', 'label' => 'Valid'],
            ],
        ]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', '/api/assistant-setting-values', $this->storePayload($setting, '', $assistant));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/value');
    }
}
