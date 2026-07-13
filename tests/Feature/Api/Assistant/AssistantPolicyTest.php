<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFeedback;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\AssistantTag;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversNothing()]
class AssistantPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Privileged relationships exposed via a related-resource URL: only creator
     * or org admin may read.
     */
    #[DataProvider('privilegedRelatedUrls')]
    public function testPrivilegedRelatedUrlMatrix(string $relation): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/hawki/v1/assistants/{$assistant->id}/{$relation}";

        $this->actingAsUser($users['owner']);
        $this->jsonApiRaw('get', $url)->assertOk();

        $this->actingAsUser($users['admin']);
        $this->jsonApiRaw('get', $url)->assertOk();

        $this->actingAsUser($users['member']);
        $this->jsonApiRaw('get', $url)->assertForbidden();

        $this->actingAsUser($users['shared']);
        $this->jsonApiRaw('get', $url)->assertForbidden();

        $this->actingAsUser($users['public']);
        $this->jsonApiRaw('get', $url)->assertForbidden();
    }

    public static function privilegedRelatedUrls(): iterable
    {
        yield from [
            'setting values' => ['assistant-setting-values'],
            'review' => ['assistant-review'],
        ];
    }

    /**
     * Feedback has no related-resource URL; it is only reachable via include
     * paths, which are subject to the same privileged policy.
     */
    #[DataProvider('privilegedIncludes')]
    public function testPrivilegedIncludeMatrix(string $include, string $type): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/hawki/v1/assistants/{$assistant->id}?include={$include}";

        $this->actingAsUser($users['owner']);
        $ownerResp = $this->jsonApiRaw('get', $url)->assertOk();
        self::assertNotEmpty(collect($ownerResp->json('included') ?? [])->where('type', $type));

        $this->actingAsUser($users['admin']);
        $adminResp = $this->jsonApiRaw('get', $url)->assertOk();
        self::assertNotEmpty(collect($adminResp->json('included') ?? [])->where('type', $type));

        foreach (['member', 'shared', 'public'] as $role) {
            $this->actingAsUser($users[$role]);
            $this->jsonApiRaw('get', $url)->assertForbidden();
        }
    }

    public static function privilegedIncludes(): iterable
    {
        yield from [
            'feedback' => ['assistant_feedback', 'assistant-feedback'],
            'setting values' => ['assistant_setting_values', 'assistant-setting-values'],
            'review' => ['assistant_review', 'assistant-reviews'],
        ];
    }

    public function testTagsVisibleToAllViewers(): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_tags";

        foreach ($users as $user) {
            $this->actingAsUser($user);
            $response = $this->jsonApiRaw('get', $url)->assertOk();
            self::assertNotEmpty(collect($response->json('included') ?? [])->where('type', 'assistant-tags'));
        }
    }

    public function testUserPromptsVisibleToAllViewers(): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $this->seedChildren($assistant);

        $url = "/api/hawki/v1/assistants/{$assistant->id}/assistant-user-prompts";

        foreach ($users as $user) {
            $this->actingAsUser($user);
            $this->jsonApiRaw('get', $url)->assertOk();
        }
    }

    public function testAiToolsVisibleToCreatorSharedAndAdminOnly(): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $assistant->ai_tools()->sync([$this->createAiTool()->id]);

        $url = "/api/hawki/v1/assistants/{$assistant->id}/ai-tools";

        $this->actingAsUser($users['owner']);
        $this->jsonApiRaw('get', $url)->assertOk();

        $this->actingAsUser($users['admin']);
        $this->jsonApiRaw('get', $url)->assertOk();

        $this->actingAsUser($users['shared']);
        $this->jsonApiRaw('get', $url)->assertOk();

        $this->actingAsUser($users['member']);
        $this->jsonApiRaw('get', $url)->assertForbidden();

        $this->actingAsUser($users['public']);
        $this->jsonApiRaw('get', $url)->assertForbidden();
    }

    /**
     * Editing ai_tools (attach/detach) requires the privileged tier
     * (creator or org admin); shared users may view but not edit.
     */
    #[DataProvider('aiToolsEditRoles')]
    public function testAiToolsAttachRequiresPrivileged(string $role, int $expected): void
    {
        [$assistant, $users] = $this->stakeholderAssistant();
        $tool = $this->createAiTool();

        $this->actingAsUser($users[$role]);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/relationships/ai-tools", [
            'data' => [['type' => 'ai-tools', 'id' => (string) $tool->id]],
        ])->assertStatus($expected);
    }

    public static function aiToolsEditRoles(): iterable
    {
        yield from [
            'owner' => ['owner', 204],
            'admin' => ['admin', 204],
            'member' => ['member', 403],
            'shared' => ['shared', 403],
            'public' => ['public', 403],
        ];
    }

    public function testIndexSensitiveIncludeNarrowsToPrivilegedAssistants(): void
    {
        $org = Organization::first();
        $owner = User::factory()->create();
        $owned = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->seedChildren($owned);

        $other = Assistant::factory()->create([
            'creator_id' => User::factory()->create()->id,
            'organization_id' => $org->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->seedChildren($other);

        // Without the sensitive include, the owner sees both public assistants.
        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')->assertOk()->assertJsonCount(2, 'data');

        // With the sensitive include, the collection narrows to assistants the
        // owner is privileged for (their own), excluding the other public one.
        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=assistant_setting_values')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        self::assertContains((string) $owned->id, $ids);
        self::assertNotContains((string) $other->id, $ids);

        // A public viewer is privileged for none, so they see an empty set.
        $public = User::factory()->create();
        $this->actingAsUser($public);
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=assistant_setting_values')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testPrivilegedRelationBlockedWhenAssistantHasNoOrganization(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'organization_id' => null,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $this->seedChildren($assistant);

        // An admin of some other organization is NOT privileged when the
        // assistant itself has no organization.
        $admin = User::factory()->create();
        $admin->organizations()->attach(Organization::first()->id, ['role' => 'admin']);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/assistant-setting-values")
            ->assertForbidden();

        $this->actingAsUser($owner);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/assistant-setting-values")
            ->assertOk();
    }

    /**
     * Build an organizational (public-stage) assistant with a full set of
     * stakeholders: owner (creator), org admin, org member, shared user, and
     * an unrelated public viewer. Every stakeholder can `view` the assistant
     * itself (it is public), so relationship access is governed purely by the
     * per-relationship policy methods.
     */
    private function stakeholderAssistant(string $stage = AssistantReleaseStage::ORGANIZATIONAL->value, ?int $orgId = null): array
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

        $assistant->assistantTags()->attach(AssistantTag::firstOrCreate(['text' => 'sample']));
        AssistantFeedback::create(['assistant_id' => $assistant->id, 'user_id' => $assistant->creator_id, 'text' => 'nice']);
        AssistantReview::create(['assistant_id' => $assistant->id, 'status' => AssistantReviewStatus::PENDING->value]);
        $assistant->assistantUserPrompts()->create(['text' => 'hello']);
    }

    private function createAiTool(): AiTool
    {
        $serverId = DB::table('mcp_servers')->insertGetId([
            'url' => 'https://example.com/mcp/' . uniqid(),
            'server_label' => 'Test Server ' . uniqid(),
            'api_key' => 'test-key',
            'timeouts' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AiTool::create([
            'type' => 'function',
            'name' => 'test_tool_' . uniqid(),
            'description' => 'A test tool',
            'active' => true,
            'mcp_server_id' => $serverId,
        ]);
    }
}
