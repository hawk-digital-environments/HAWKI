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

#[CoversNothing()]
class AssistantSettingsTest extends TestCase
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

    public function testOwnerCanCreateSettingValue(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_setting_values', [
            'assistant_id' => $assistant->id,
            'setting_id' => $this->formality->id,
            'value' => json_encode('professional'),
        ]);
    }

    public function testCreateRejectsDuplicateSettingPerAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertStatus(422);

        self::assertSame(1, $assistant->settingValues()->where('setting_id', $this->formality->id)->count());
    }

    public function testCreateRejectsInvalidValue(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'telepathic', $assistant));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/value');
    }

    public function testOwnerCanUpdateValue(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $value = $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-setting-values/{$value->id}", [
            'data' => [
                'type' => 'assistant-setting-values',
                'id' => (string) $value->id,
                'attributes' => ['value' => 'professional'],
            ],
        ])->assertOk();

        self::assertSame('professional', $assistant->fresh()->settingValues->firstWhere('setting_id', $this->formality->id)->value);
    }

    public function testUpdateRejectsInvalidValue(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $value = $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-setting-values/{$value->id}", [
            'data' => [
                'type' => 'assistant-setting-values',
                'id' => (string) $value->id,
                'attributes' => ['value' => 'telepathic'],
            ],
        ])->assertStatus(422);
    }

    public function testNonOwnerCannotCreate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertForbidden();

        $this->assertDatabaseMissing('assistant_setting_values', ['assistant_id' => $assistant->id]);
    }

    public function testGuestCannotCreate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertStatus(401);
    }

    public function testSettingChangeRecordsVersionWhenOrganizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertEquals(['assistant_setting_values'], $version->changed_keys);
        self::assertSame('{"changes":["assistant_setting_values"]}', $version->text);
    }

    public function testSettingChangeSkipsVersionWhenPrivate(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
    }

    public function testRapidSettingChangesAreDebouncedIntoOneVersion(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        // Two rapid setting-value creates within the debounce window merge into one version.
        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, 'professional', $assistant))
            ->assertCreated();

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->language, 'de', $assistant))
            ->assertCreated();

        self::assertSame($initialVersionCount, $assistant->fresh()->assistantVersions()->count());
        self::assertSame(2, $assistant->fresh()->settingValues()->count());
    }

    public function testOwnerCanStoreEmptyValueForEmptyTolerantSetting(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($this->formality, '', $assistant))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_setting_values', [
            'assistant_id' => $assistant->id,
            'setting_id' => $this->formality->id,
            'value' => json_encode(''),
        ]);
    }

    public function testOwnerCanUpdateToEmptyValue(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $value = $assistant->settingValues()->create([
            'setting_id' => $this->formality->id,
            'value' => 'casual',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-setting-values/{$value->id}", [
            'data' => [
                'type' => 'assistant-setting-values',
                'id' => (string) $value->id,
                'attributes' => ['value' => ''],
            ],
        ])->assertOk();

        self::assertSame('', $assistant->fresh()->settingValues->firstWhere('setting_id', $this->formality->id)->value);
    }

    public function testEmptyValueRejectedForSettingWithoutEmptyOption(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $setting = AssistantSetting::factory()->create([
            'ui_options' => [
                ['value' => 'only', 'label' => 'Only'],
                ['value' => 'valid', 'label' => 'Valid'],
            ],
        ]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistant-setting-values', $this->storePayload($setting, '', $assistant));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/value');
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
}
