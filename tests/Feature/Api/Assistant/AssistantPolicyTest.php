<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Ai\Tools\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\Feedback;
use App\Models\Assistants\Review;
use App\Models\Assistants\Tag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssistantPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an organizational (public-stage) assistant with a full set of
     * stakeholders: owner (creator), org admin, org member, shared user, and
     * an unrelated public viewer. Every stakeholder can `view` the assistant
     * itself (it is public), so relationship access is governed purely by the
     * per-relationship policy methods.
     */
    private function stakeholderAssistant(string $stage = ReleaseStage::ORGANIZATIONAL->value, ?int $orgId = null): array
    {
        $orgId ??= Organization::first()->id;

        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $orgId,
            'release_stage' => $stage,
        ]);

        $admin = User::factory()->create();
        $admin->organizations()->attach($assistant->organization_id, ['role' => 'admin']);

        $member = User::factory()->create();
        $member->organizations()->attach($assistant->organization_id, ['role' => 'member']);

        $shared = User::factory()->create();
        $assistant->sharedUsers()->sync([$shared->id]);

        $public = User::factory()->create();

        return [
            $assistant,
            ['owner' => $owner, 'admin' => $admin, 'member' => $member, 'shared' => $shared, 'public' => $public],
        ];
    }

    private function seedChildren(Assistant $assistant): void
    {
        $setting = AssistantSetting::factory()->create();

        AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => ['formality' => 'professional'],
        ]);

        $assistant->tags()->attach(Tag::firstOrCreate(['text' => 'sample']));
        Feedback::create(['assistant_id' => $assistant->id, 'user_id' => $assistant->creator_id, 'text' => 'nice']);
        Review::create(['assistant_id' => $assistant->id, 'status' => ReviewStatus::PENDING->value]);
        $assistant->user_prompts()->create(['text' => 'hello']);
    }

    private function createAiTool(): AiTool
    {
        $serverId = DB::table('mcp_servers')->insertGetId([
            'url' => 'https://example.com/mcp/'.uniqid(),
            'server_label' => 'Test Server '.uniqid(),
            'timeout' => '10',
            'discovery_timeout' => '10',
            'api_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AiTool::create([
            'type' => 'function',
            'name' => 'test_tool_'.uniqid(),
            'description' => 'A test tool',
            'status' => 'active',
            'server_id' => $serverId,
        ]);
    }

    /**
     * Privileged relationships exposed via a related-resource URL: only creator
     * or org admin may read.
     */
    #[DataProvider('privilegedRelatedUrls')]
    public function test_privileged_related_url_matrix(string $relation): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/assistants/{$assistant->id}/{$relation}";

        Sanctum::actingAs($users['owner']);
        $this->jsonApi('get', $url)->assertOk();

        Sanctum::actingAs($users['admin']);
        $this->jsonApi('get', $url)->assertOk();

        Sanctum::actingAs($users['member']);
        $this->jsonApi('get', $url)->assertForbidden();

        Sanctum::actingAs($users['shared']);
        $this->jsonApi('get', $url)->assertForbidden();

        Sanctum::actingAs($users['public']);
        $this->jsonApi('get', $url)->assertForbidden();
    }

    public static function privilegedRelatedUrls(): array
    {
        return [
            'setting values' => ['assistant-setting-values'],
            'review' => ['assistant-review'],
        ];
    }

    /**
     * Feedback has no related-resource URL; it is only reachable via include
     * paths, which are subject to the same privileged policy.
     */
    #[DataProvider('privilegedIncludes')]
    public function test_privileged_include_matrix(string $include, string $type): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/assistants/{$assistant->id}?include={$include}";

        Sanctum::actingAs($users['owner']);
        $ownerResp = $this->jsonApi('get', $url)->assertOk();
        $this->assertNotEmpty(collect($ownerResp->json('included') ?? [])->where('type', $type));

        Sanctum::actingAs($users['admin']);
        $this->jsonApi('get', $url)->assertOk();

        foreach (['member', 'shared', 'public'] as $role) {
            Sanctum::actingAs($users[$role]);
            $this->jsonApi('get', $url)->assertForbidden();
        }
    }

    public static function privilegedIncludes(): array
    {
        return [
            'feedback' => ['assistant_feedback', 'assistant-feedback'],
            'setting values' => ['assistant_setting_values', 'assistant-setting-values'],
            'review' => ['assistant_review', 'assistant-reviews'],
        ];
    }

    public function test_tags_visible_to_all_viewers(): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/assistants/{$assistant->id}?include=assistant_tags";

        foreach ($users as $user) {
            Sanctum::actingAs($user);
            $response = $this->jsonApi('get', $url)->assertOk();
            $this->assertNotEmpty(
                collect($response->json('included') ?? [])->where('type', 'assistant-tags'),
            );
        }
    }

    public function test_user_prompts_visible_to_all_viewers(): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/assistants/{$assistant->id}/assistant-user-prompts";

        foreach ($users as $user) {
            Sanctum::actingAs($user);
            $this->jsonApi('get', $url)->assertOk();
        }
    }

    public function test_ai_tools_visible_to_creator_shared_and_admin_only(): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $assistant->ai_tools()->sync([$this->createAiTool()->id]);

        $url = "/api/assistants/{$assistant->id}/ai-tools";

        Sanctum::actingAs($users['owner']);
        $this->jsonApi('get', $url)->assertOk();

        Sanctum::actingAs($users['admin']);
        $this->jsonApi('get', $url)->assertOk();

        Sanctum::actingAs($users['shared']);
        $this->jsonApi('get', $url)->assertOk();

        Sanctum::actingAs($users['member']);
        $this->jsonApi('get', $url)->assertForbidden();

        Sanctum::actingAs($users['public']);
        $this->jsonApi('get', $url)->assertForbidden();
    }

    /**
     * Editing ai_tools (attach/detach) requires the privileged tier
     * (creator or org admin); shared users may view but not edit.
     */
    #[DataProvider('aiToolsEditRoles')]
    public function test_ai_tools_attach_requires_privileged(string $role, int $expected): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $tool = $this->createAiTool();

        Sanctum::actingAs($users[$role]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertStatus($expected);
    }

    public static function aiToolsEditRoles(): array
    {
        return [
            'owner' => ['owner', 204],
            'admin' => ['admin', 204],
            'member' => ['member', 403],
            'shared' => ['shared', 403],
            'public' => ['public', 403],
        ];
    }

    public function test_index_sensitive_include_narrows_to_privileged_assistants(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $owned = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->seedChildren($owned);

        $other = Assistant::factory()->create([
            'creator_id' => User::factory()->create()->id,
            'organization_id' => $org->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->seedChildren($other);

        // Without the sensitive include, the owner sees both public assistants.
        Sanctum::actingAs($owner);
        $this->jsonApi('get', '/api/assistants')->assertOk()->assertJsonCount(2, 'data');

        // With the sensitive include, the collection narrows to assistants the
        // owner is privileged for (their own), excluding the other public one.
        $response = $this->jsonApi('get', '/api/assistants?include=assistant_setting_values')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((string) $owned->id, $ids);
        $this->assertNotContains((string) $other->id, $ids);

        // A public viewer is privileged for none, so they see an empty set.
        $public = User::factory()->create();
        Sanctum::actingAs($public);
        $this->jsonApi('get', '/api/assistants?include=assistant_setting_values')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_privileged_relation_blocked_when_assistant_has_no_organization(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => null,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->seedChildren($assistant);

        // An admin of some other organization is NOT privileged when the
        // assistant itself has no organization.
        $admin = User::factory()->create();
        $admin->organizations()->attach(Organization::first()->id, ['role' => 'admin']);

        Sanctum::actingAs($admin);
        $this->jsonApi('get', "/api/assistants/{$assistant->id}/assistant-setting-values")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->jsonApi('get', "/api/assistants/{$assistant->id}/assistant-setting-values")
            ->assertOk();
    }
}
