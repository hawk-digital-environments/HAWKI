<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\User;
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
                ['value' => 'casual'],
                ['value' => 'balanced'],
                ['value' => 'professional'],
                ['value' => 'academic'],
            ],
        ]);

        $this->language = AssistantSetting::factory()->create([
            'key' => 'language',
            'ui_options' => [
                ['value' => 'en'],
                ['value' => 'de'],
            ],
        ]);

        $this->answerLength = AssistantSetting::factory()->create([
            'key' => 'answer_length',
            'ui_options' => [
                ['value' => 'concise'],
                ['value' => 'balanced'],
                ['value' => 'detailed'],
            ],
        ]);
    }

    private function settingsPayload(string $assistantId, array $settings): array
    {
        return [
            'data' => [
                'type' => 'assistants',
                'id' => $assistantId,
                'attributes' => ['settings' => $settings],
            ],
        ];
    }

    public function test_creator_can_update_multiple_settings_at_once(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'formality', 'value' => 'professional'],
            ['key' => 'language', 'value' => 'de'],
            ['key' => 'answer_length', 'value' => 'concise'],
        ]))->assertSuccessful();

        $values = $assistant->fresh()->load('settingValues')->settingValues;

        $this->assertSame('professional', $values->firstWhere('setting_id', $this->formality->id)->value);
        $this->assertSame('de', $values->firstWhere('setting_id', $this->language->id)->value);
        $this->assertSame('concise', $values->firstWhere('setting_id', $this->answerLength->id)->value);
    }

    public function test_upsert_updates_existing_value_not_duplicate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'formality', 'value' => 'professional'],
        ]))->assertSuccessful();

        $this->assertSame(1, $assistant->settingValues()->where('setting_id', $this->formality->id)->count());
        $this->assertSame(
            'professional',
            $assistant->fresh()->settingValues->firstWhere('setting_id', $this->formality->id)->value
        );
    }

    public function test_unknown_setting_key_returns_422_with_pointer(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'bogus', 'value' => 'x'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/settings/0/key');
    }

    public function test_invalid_value_returns_422_with_pointer(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'formality', 'value' => 'telepathic'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/settings/0/value');
    }

    public function test_duplicate_setting_key_returns_422_with_pointer(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'formality', 'value' => 'professional'],
            ['key' => 'formality', 'value' => 'casual'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/settings/1/key');
    }

    public function test_non_creator_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'formality', 'value' => 'professional'],
        ]))->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", $this->settingsPayload((string) $assistant->id, [
            ['key' => 'formality', 'value' => 'professional'],
        ]))->assertUnauthorized();
    }

    public function test_requires_settings_attribute(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/settings", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [],
            ],
        ])->assertStatus(422);
    }
}
