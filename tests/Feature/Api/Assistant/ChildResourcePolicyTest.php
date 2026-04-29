<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\Tag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChildResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_values_index_scoped_to_privileged_assistants(): void
    {
        $org = Organization::first();

        $owner = User::factory()->create();
        $owned = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);

        $otherOwner = User::factory()->create();
        $inaccessible = Assistant::factory()->create([
            'creator_id' => $otherOwner->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);

        $setting = AssistantSetting::factory()->create();
        $ownedValue = AssistantSettingValue::create([
            'assistant_id' => $owned->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);
        AssistantSettingValue::create([
            'assistant_id' => $inaccessible->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'casual'],
        ]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('get', '/api/assistant-setting-values')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((string) $ownedValue->id, $ids);
        $this->assertNotContains((string) AssistantSettingValue::where('assistant_id', $inaccessible->id)->value('id'), $ids);
    }

    public function test_setting_values_show_forbidden_to_non_privileged_viewer(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $setting = AssistantSetting::factory()->create();
        $value = AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        // A shared user can view the assistant but is not privileged for settings.
        $shared = User::factory()->create();
        $assistant->sharedUsers()->sync([$shared->id]);

        Sanctum::actingAs($shared);
        $this->jsonApi('get', "/api/assistant-setting-values/{$value->id}")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->jsonApi('get', "/api/assistant-setting-values/{$value->id}")
            ->assertOk();
    }

    public function test_tags_index_visible_to_any_authenticated_user(): void
    {
        // Tags are a global, shared library of unique labels (no ownership,
        // no per-assistant scoping). Any authenticated user can list and show
        // every tag, since a tag carries only its `text`.
        $ownedTag = Tag::create(['text' => 'php']);
        $otherTag = Tag::create(['text' => 'laravel']);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $ids = collect($this->jsonApi('get', '/api/assistant-tags')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertContains((string) $ownedTag->id, $ids);
        $this->assertContains((string) $otherTag->id, $ids);

        $this->jsonApi('get', "/api/assistant-tags/{$otherTag->id}")->assertOk();
    }

    public function test_org_admin_sees_org_assistants_setting_values(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $setting = AssistantSetting::factory()->create();
        $value = AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        $admin = User::factory()->create();
        $admin->organizations()->attach($org, ['role' => 'admin']);

        Sanctum::actingAs($admin);
        $this->jsonApi('get', "/api/assistant-setting-values/{$value->id}")
            ->assertOk();

        $ids = collect($this->jsonApi('get', '/api/assistant-setting-values')->assertOk()->json('data'))
            ->pluck('id');
        $this->assertContains((string) $value->id, $ids);
    }
}
