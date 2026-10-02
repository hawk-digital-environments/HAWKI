<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant\Fixtures;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiTool;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\RoleAssignmentService;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Support\Facades\DB;

trait Assistant
{
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Assistant',
            'system_prompt' => 'You are a helpful assistant.',
            'greeting' => 'Hello!',
            'description' => 'A test assistant.',
            'detail_description' => 'Detailed description here.',
            'allow_remix' => true,
            'allow_model_select' => false,
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temp' => 0.7,
            'top_p' => 0.9,
        ], $overrides);
    }

    private function createRelationships(array $rels = []): array
    {
        $defaults = [];

        if (isset($rels['assistant_category'])) {
            $defaults['assistant_category'] = ['data' => ['type' => 'assistant-categories', 'id' => (string) $rels['assistant_category']]];
        }

        if (isset($rels['assistant_tags'])) {
            $defaults['assistant_tags'] = ['data' => array_map(static fn ($id) => ['type' => 'assistant-tags', 'id' => (string) $id], $rels['assistant_tags'])];
        }

        if (isset($rels['ai_tools'])) {
            $defaults['ai_tools'] = ['data' => array_map(static fn ($id) => ['type' => 'ai-tools', 'id' => (string) $id], $rels['ai_tools'])];
        }

        if (isset($rels['assistant_user_prompts'])) {
            $defaults['assistant_user_prompts'] = ['data' => array_map(static fn ($id) => ['type' => 'assistant-user-prompts', 'id' => (string) $id], $rels['assistant_user_prompts'])];
        }

        return $defaults;
    }

    private function createJsonApiPayload(array $attrOverrides = [], array $relOverrides = []): array
    {
        $doc = [
            'data' => [
                'type' => 'assistants',
                'attributes' => $this->createPayload($attrOverrides),
            ],
        ];
        $rels = $this->createRelationships($relOverrides);

        if ($rels) {
            $doc['data']['relationships'] = $rels;
        }

        return $doc;
    }

    /**
     * A discoverable test tool: since the RBAC tool-access rework, every
     * API-path ai_tools query runs through the `tool_discovery` global scope
     * ({@see \App\Models\Ai\AiTool::booted()}), so a fixture tool needs an
     * access rule, an acting user holding that rule's grants (see
     * {@see grantInternalToolAccess()}), and linkage to an active
     * tool-calling model — otherwise relationship reads/writes 404.
     */
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

        $tool = AiTool::create([
            'type' => 'function',
            'name' => 'test_tool_' . uniqid(),
            'description' => 'A test tool',
            'active' => true,
            'access_rule' => 'internal_search',
            'mcp_server_id' => $serverId,
        ]);

        $provider = AiProvider::create([
            'provider_id' => 'assistant-fixture-' . uniqid(),
            'name' => 'Assistant fixture provider',
            'active' => true,
            'adapter_key' => 'openai',
            'api_url' => 'https://example.invalid',
            'api_key' => 'test-only',
        ]);

        $model = AiModel::create([
            'model_id' => 'assistant-fixture-' . uniqid(),
            'label' => 'Assistant fixture model',
            'provider_id' => $provider->id,
            'active' => true,
            'status' => OnlineStatus::ONLINE,
            'flags' => AiModelFlags::fromArray([]),
            'limits' => new NullAiModelLimits(),
            'pricing' => new NullPricing(),
            'settings' => AiModelSettings::fromArray(['tool_calling' => true, 'native_capabilities' => true]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
        ]);
        $model->usageRules()->create(['usage_type' => WellKnownUsageTypes::MAIN_APP]);
        $model->tools()->attach($tool->id, ['type' => 'manual']);

        return $tool;
    }

    /**
     * Grants the internal-search tool access rule to a user, mirroring the
     * RBAC setup of {@see \Tests\Feature\ToolAuthorizationTest}: without the
     * `tools.use` + `tools.internal_search.use` grants the tool created by
     * {@see createAiTool()} stays invisible to them.
     */
    private function grantInternalToolAccess(User $user): void
    {
        $role = Role::create([
            'name' => 'assistant-fixture-tool-grant-' . uniqid(),
            'display_name' => 'Assistant fixture tool grant',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['tools.use', 'tools.internal_search.use']);

        app(RoleAssignmentService::class)->replace($user, [$role->id]);
    }
}
