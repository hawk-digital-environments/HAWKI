<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\Category;
use App\Models\Assistants\Feedback;
use App\Models\Assistants\Review;
use App\Models\Assistants\Tag;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientSchemaTest extends TestCase
{
    private User $user;

    private Assistant $assistant;

    private Category $category;

    private Tag $tag1;

    private Tag $tag2;

    private AssistantSetting $settingLang;

    private AssistantSetting $settingFormality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->category = Category::factory()->create(['text' => 'research']);

        $this->settingLang = AssistantSetting::factory()->create([
            'key' => 'language',
            'ui_options' => [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'de', 'label' => 'German'],
            ],
        ]);
        $this->settingFormality = AssistantSetting::factory()->create([
            'key' => 'formality',
            'ui_options' => [
                ['value' => 'casual', 'label' => 'Casual'],
                ['value' => 'balanced', 'label' => 'Balanced'],
                ['value' => 'professional', 'label' => 'Professional'],
                ['value' => 'academic', 'label' => 'Academic'],
            ],
        ]);

        $this->assistant = Assistant::factory()->create([
            'name' => 'Schema Test Assistant',
            'handle' => 'schema-test',
            'system_prompt' => 'You are a test assistant.',
            'greeting' => 'Hello from the schema test!',
            'description' => 'An assistant for testing client schema generation.',
            'detail_description' => 'Detailed description for schema test.',
            'allow_remix' => true,
            'allow_model_select' => false,
            'release_stage' => ReleaseStage::DRAFT->value,
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temp' => 0.7,
            'top_p' => 0.95,
            'creator_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->tag1 = Tag::create(['text' => 'php']);
        $this->tag2 = Tag::create(['text' => 'javascript']);
        $this->assistant->tags()->attach([$this->tag1->id, $this->tag2->id]);

        AssistantSettingValue::create([
            'assistant_id' => $this->assistant->id,
            'setting_id' => $this->settingLang->id,
            'value' => 'en',
        ]);

        Feedback::create([
            'assistant_id' => $this->assistant->id,
            'user_id' => $this->user->id,
            'text' => 'Great assistant!',
        ]);

        Review::create([
            'assistant_id' => $this->assistant->id,
            'status' => 'approved',
            'reason' => null,
        ]);
    }

    public function test_schema_requires_authentication(): void
    {
        $this->jsonApi('get', '/api/assistants/schema')
            ->assertUnauthorized();
    }

    public function test_schema_returns_all_resource_types(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');

        $response->assertOk();

        $resources = $response->json('resources');

        $expectedTypes = [
            'assistants', 'assistant-avatars', 'assistant-categories',
            'assistant-settings', 'assistant-setting-values', 'users',
            'assistant-tags', 'assistant-user-prompts', 'ai-tools', 'mcp-servers',
            'ai-models', 'ai-model-statuses', 'ai-providers',
            'assistant-reviews', 'versions', 'organizations', 'assistant-feedback',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, $resources, "Missing resource type: {$type}");
        }

        $this->assertCount(17, $resources);
        $this->assertSame('1.0', $response->json('version'));
        $this->assertNotNull($response->json('generatedAt'));
    }

    public function test_schema_top_level_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');

        $response->assertOk()->assertJsonStructure([
            'version',
            'generatedAt',
            'resources',
        ]);
    }

    public function test_assistant_resource_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $a = $response->json('resources.assistants');

        $this->assertSame('assistants', $a['type']);
        $this->assertSame('Assistants', $a['displayName']);

        $requiredKeys = ['type', 'displayName', 'endpoints',
            'attributes', 'relationships', 'actions', 'filters', 'sortable', 'includable'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $a, "Missing key: {$key}");
        }
    }

    public function test_assistant_endpoints(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $endpoints = $response->json('resources.assistants.endpoints');

        $expected = ['list', 'create', 'read', 'update', 'delete'];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $endpoints);
            $this->assertArrayHasKey('method', $endpoints[$name]);
            $this->assertArrayHasKey('url', $endpoints[$name]);
        }

        $this->assertSame('GET', $endpoints['list']['method']);
        $this->assertSame('/api/assistants', $endpoints['list']['url']);
        $this->assertTrue($endpoints['list']['allowed']);
        $this->assertSame('POST', $endpoints['create']['method']);
        $this->assertSame('PATCH', $endpoints['update']['method']);
        $this->assertSame('/api/assistants/{id}', $endpoints['update']['url']);
        $this->assertSame('DELETE', $endpoints['delete']['method']);
        $this->assertTrue($endpoints['update']['allowed']);
        $this->assertTrue($endpoints['delete']['allowed']);
        $this->assertTrue($endpoints['read']['allowed']);
    }

    public function test_assistant_attributes_exist(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        $expectedFields = [
            'name', 'handle', 'system_prompt', 'greeting', 'description',
            'detail_description', 'allow_remix', 'allow_model_select',
            'release_stage', 'model', 'max_tokens', 'temp', 'top_p',
            'created_at', 'updated_at', 'is_favorite',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $attrs, "Missing attribute: {$field}");
        }

        // version_text must NOT appear (hidden field)
        $this->assertArrayNotHasKey('version_text', $attrs, 'Hidden field version_text must not appear');

        // Every attribute must have a type
        foreach ($attrs as $name => $attr) {
            $this->assertArrayHasKey('type', $attr, "Attribute '{$name}' missing type");
        }
    }

    public function test_attribute_types(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        $this->assertSame('string', $attrs['name']['type']);
        $this->assertSame('string', $attrs['handle']['type']);
        $this->assertSame('string', $attrs['system_prompt']['type']);
        $this->assertSame('boolean', $attrs['allow_remix']['type']);
        $this->assertSame('boolean', $attrs['allow_model_select']['type']);
        $this->assertSame('enum', $attrs['release_stage']['type']);
        $this->assertSame('number', $attrs['max_tokens']['type']);
        $this->assertSame('number', $attrs['temp']['type']);
        $this->assertSame('number', $attrs['top_p']['type']);
        $this->assertSame('datetime', $attrs['created_at']['type']);
        $this->assertSame('datetime', $attrs['updated_at']['type']);
        $this->assertSame('boolean', $attrs['is_favorite']['type']);
    }

    public function test_readonly_attributes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        $this->assertTrue($attrs['created_at']['readOnly']);
        $this->assertTrue($attrs['updated_at']['readOnly']);
        $this->assertTrue($attrs['is_favorite']['readOnly']);

        // Writable fields must NOT be readOnly
        $this->assertArrayNotHasKey('readOnly', $attrs['name']);
        $this->assertArrayNotHasKey('readOnly', $attrs['system_prompt']);
    }

    public function test_attribute_constraints(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        // String constraint
        $this->assertSame(255, $attrs['name']['constraints']['maxLength']);

        // Enum constraint
        $this->assertSame(
            ['draft', 'private', 'organizational', 'federated'],
            $attrs['release_stage']['constraints']['values'],
        );

        // Numeric constraints
        $this->assertSame(0, $attrs['max_tokens']['constraints']['minimum']);
        $this->assertTrue($attrs['max_tokens']['constraints']['integer']);

        $this->assertSame(0, $attrs['temp']['constraints']['minimum']);
        $this->assertSame(1, $attrs['temp']['constraints']['maximum']);

        $this->assertSame(0, $attrs['top_p']['constraints']['minimum']);
        $this->assertSame(1, $attrs['top_p']['constraints']['maximum']);
    }

    public function test_writable_on_resource_attributes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        // Writable via main resource endpoint
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['name']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['handle']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['system_prompt']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['greeting']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['description']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['detail_description']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['allow_remix']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['allow_model_select']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['model']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['max_tokens']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['temp']['writable_on']);
        $this->assertSame([['method' => 'PATCH', 'path' => '/api/assistants/{id}']], $attrs['top_p']['writable_on']);
    }

    public function test_writable_on_dual_path_attributes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        $this->assertSame(
            [['method' => 'PATCH', 'path' => '/api/assistants/{id}']],
            $attrs['release_stage']['writable_on'],
        );
    }

    public function test_writable_on_action_only_attributes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        // is_favorite is computed (readOnly), managed via self-service actions.
        $this->assertSame(
            [
                ['method' => 'POST',   'path' => '/api/assistants/{id}/actions/favorite'],
                ['method' => 'DELETE', 'path' => '/api/assistants/{id}/actions/favorite'],
            ],
            $attrs['is_favorite']['writable_on'],
        );
    }

    public function test_readonly_attributes_have_no_writable_on(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        $this->assertArrayNotHasKey('writable_on', $attrs['created_at']);
        $this->assertArrayNotHasKey('writable_on', $attrs['updated_at']);
    }

    public function test_relationships_exist(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        $expected = [
            'category', 'assistant_avatar', 'assistant_setting_values', 'assistant_user_prompts', 'ai_tools',
            'assistant_tags', 'creator', 'remix_creator', 'remixed_assistant',
            'versions', 'organization', 'assistant_review', 'assistant_feedback', 'shared_users',
        ];

        foreach ($expected as $rel) {
            $this->assertArrayHasKey($rel, $rels, "Missing relationship: {$rel}");
        }

        $this->assertCount(14, $rels);
    }

    public function test_relationship_cardinality(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        $this->assertSame('toOne', $rels['category']['cardinality']);
        $this->assertSame('toOne', $rels['assistant_avatar']['cardinality']);
        $this->assertSame('toOne', $rels['creator']['cardinality']);
        $this->assertSame('toOne', $rels['assistant_review']['cardinality']);
        $this->assertSame('toMany', $rels['assistant_tags']['cardinality']);
        $this->assertSame('toMany', $rels['versions']['cardinality']);
        $this->assertSame('toMany', $rels['assistant_feedback']['cardinality']);
        $this->assertSame('toMany', $rels['assistant_setting_values']['cardinality']);
    }

    public function test_relationship_types(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        $this->assertSame('assistant-categories', $rels['category']['type']);
        $this->assertSame('assistant-avatars', $rels['assistant_avatar']['type']);
        $this->assertSame('assistant-setting-values', $rels['assistant_setting_values']['type']);
        $this->assertSame('users', $rels['creator']['type']);
        $this->assertSame('assistant-tags', $rels['assistant_tags']['type']);
        $this->assertSame('assistant-reviews', $rels['assistant_review']['type']);
    }

    public function test_relationship_writable_on(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        $patch = [['method' => 'PATCH', 'path' => '/api/assistants/{id}']];

        // Writable via main resource endpoint
        // Writable via main resource endpoint + relationship endpoint (to-one: PATCH only)
        $this->assertSame([
            ['method' => 'PATCH', 'path' => '/api/assistants/{id}'],
            ['method' => 'PATCH', 'path' => '/api/assistants/{id}/relationships/category'],
        ], $rels['category']['writable_on']);

        $this->assertSame([
            ['method' => 'PATCH', 'path' => '/api/assistants/{id}'],
            ['method' => 'POST', 'path' => '/api/assistants/{id}/relationships/ai-tools'],
            ['method' => 'DELETE', 'path' => '/api/assistants/{id}/relationships/ai-tools'],
            ['method' => 'PATCH', 'path' => '/api/assistants/{id}/relationships/ai-tools'],
        ], $rels['ai_tools']['writable_on']);

        $this->assertSame([
            ['method' => 'PATCH', 'path' => '/api/assistants/{id}'],
            ['method' => 'POST', 'path' => '/api/assistants/{id}/relationships/assistant-tags'],
            ['method' => 'DELETE', 'path' => '/api/assistants/{id}/relationships/assistant-tags'],
            ['method' => 'PATCH', 'path' => '/api/assistants/{id}/relationships/assistant-tags'],
        ], $rels['assistant_tags']['writable_on']);

        // ReadOnly relationships should NOT have writable_on
        $this->assertArrayNotHasKey('writable_on', $rels['creator']);
        $this->assertArrayNotHasKey('writable_on', $rels['versions']);
        $this->assertArrayNotHasKey('writable_on', $rels['organization']);
        $this->assertArrayNotHasKey('writable_on', $rels['remix_creator']);
        $this->assertArrayNotHasKey('writable_on', $rels['remixed_assistant']);
        $this->assertArrayNotHasKey('writable_on', $rels['assistant_user_prompts']);
        $this->assertArrayNotHasKey('writable_on', $rels['assistant_feedback']);
        $this->assertArrayNotHasKey('writable_on', $rels['assistant_setting_values']);
        $this->assertArrayNotHasKey('writable_on', $rels['assistant_review']);
    }

    public function test_relationship_endpoints(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        // Every relationship must have a fetch endpoint
        foreach ($rels as $name => $rel) {
            $this->assertArrayHasKey('fetch', $rel['endpoints'], "Relationship '{$name}' missing fetch endpoint");
            $this->assertSame('GET', $rel['endpoints']['fetch']['method']);
            $this->assertStringContainsString("{id}/{$name}", $rel['endpoints']['fetch']['url']);
        }
    }

    public function test_actions_exist(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        $expectedActions = ['chat-test', 'remix', 'favorite'];

        foreach ($expectedActions as $name) {
            $this->assertArrayHasKey($name, $actions, "Missing action: {$name}");
        }

        $this->assertCount(3, $actions);
    }

    public function test_action_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        foreach ($actions as $name => $action) {
            $this->assertArrayHasKey('method', $action);
            $this->assertArrayHasKey('url', $action);
            $this->assertArrayHasKey('allowed', $action);
            $this->assertContains($action['method'], ['POST', 'DELETE']);
            $this->assertTrue($action['allowed'], "Action '{$name}' should be allowed for authenticated user");
        }
    }

    public function test_action_urls(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        $this->assertSame('/api/assistants/{id}/actions/remix', $actions['remix']['url']);
        $this->assertSame('/api/assistants/{id}/actions/favorite', $actions['favorite']['url']);
    }

    public function test_action_input_schemas(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        // chat-test has no FormRequest → no input schema
        $this->assertArrayNotHasKey('input', $actions['chat-test']);

        // remix has no FormRequest → no input schema
        $this->assertArrayNotHasKey('input', $actions['remix']);
    }

    public function test_filters(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $filters = $response->json('resources.assistants.filters');

        $this->assertIsArray($filters);
        $this->assertNotEmpty($filters);

        $filterNames = array_column($filters, 'name');
        $this->assertContains('filter[category]', $filterNames);
        $this->assertContains('filter[name]', $filterNames);
        $this->assertContains('filter[is_favorite]', $filterNames);
        $this->assertContains('filter[release_stage]', $filterNames);
    }

    public function test_sortable(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $sortable = $response->json('resources.assistants.sortable');

        $this->assertContains('id', $sortable);
        $this->assertContains('created_at', $sortable);
        $this->assertContains('updated_at', $sortable);
    }

    public function test_includable(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $includable = $response->json('resources.assistants.includable');

        $this->assertContains('category', $includable);
        $this->assertContains('assistant_tags', $includable);
        $this->assertContains('creator', $includable);
        $this->assertContains('versions', $includable);
        $this->assertContains('assistant_setting_values', $includable);
        $this->assertContains('assistant_review', $includable);
        $this->assertContains('assistant_feedback', $includable);
    }

    public function test_authenticated_endpoint_allowed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $endpoints = $response->json('resources.assistants.endpoints');

        $this->assertTrue($endpoints['create']['allowed']);
        $this->assertTrue($endpoints['list']['allowed']);
        $this->assertTrue($endpoints['read']['allowed']);
        $this->assertTrue($endpoints['update']['allowed']);
        $this->assertTrue($endpoints['delete']['allowed']);
    }

    public function test_unauthenticated_actions_not_allowed(): void
    {
        $this->jsonApi('get', '/api/assistants/schema')
            ->assertUnauthorized();
    }

    public function test_non_authorizable_resources_have_all_endpoints_allowed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $resources = $response->json('resources');

        // Tags has no authorization (authorizable() returns false); now only index/show.
        $this->assertTrue($resources['assistant-tags']['endpoints']['list']['allowed']);

        // Categories has no authorization
        $this->assertTrue($resources['assistant-categories']['endpoints']['create']['allowed']);
        $this->assertTrue($resources['assistant-categories']['endpoints']['list']['allowed']);
    }

    public function test_other_resource_types_have_basic_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $resources = $response->json('resources');

        $standalone = ['assistants', 'assistant-avatars', 'assistant-categories', 'assistant-tags',
            'assistant-settings', 'assistant-setting-values', 'assistant-reviews',
            'assistant-user-prompts', 'assistant-feedback',
            'ai-tools', 'mcp-servers', 'ai-models', 'ai-providers'];

        foreach ($resources as $type => $resource) {
            $this->assertArrayHasKey('type', $resource, "Type '{$type}' missing type field");
            $this->assertArrayHasKey('attributes', $resource);
            $this->assertArrayHasKey('filters', $resource);
            $this->assertArrayHasKey('sortable', $resource);
            $this->assertArrayHasKey('includable', $resource);

            $this->assertSame($type, $resource['type']);

            if (in_array($type, $standalone, true)) {
                $this->assertArrayHasKey('endpoints', $resource, "Type '{$type}' missing endpoints");
            } else {
                $this->assertArrayNotHasKey('endpoints', $resource, "Type '{$type}' should not have standalone endpoints");
            }
        }
    }

    public function test_relationship_only_resources_have_no_standalone_endpoints(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $resources = $response->json('resources');

        $relationOnly = ['users', 'ai-model-statuses', 'versions', 'organizations'];

        foreach ($relationOnly as $type) {
            $this->assertArrayHasKey($type, $resources, "Missing resource: {$type}");
            $this->assertArrayNotHasKey('endpoints', $resources[$type],
                "'{$type}' should not have standalone endpoints");
            $this->assertSame($type, $resources[$type]['type']);
            $this->assertArrayHasKey('attributes', $resources[$type]);
            $this->assertArrayHasKey('filters', $resources[$type]);
            $this->assertArrayHasKey('sortable', $resources[$type]);
            $this->assertArrayHasKey('includable', $resources[$type]);
        }
    }

    public function test_enums_on_other_resources(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');
        $resources = $response->json('resources');

        // ai-tools type field is an enum from DB_ENUMS
        $aiTools = $resources['ai-tools']['attributes'];
        $this->assertSame('enum', $aiTools['type']['type']);
        $this->assertSame(['mcp', 'function'], $aiTools['type']['constraints']['values']);
        $this->assertSame('enum', $aiTools['status']['type']);
        $this->assertSame(['active', 'inactive'], $aiTools['status']['constraints']['values']);

        // ai-model-statuses status is an enum
        $statuses = $resources['ai-model-statuses']['attributes'];
        $this->assertSame('enum', $statuses['status']['type']);
        $this->assertSame(['online', 'offline', 'unknown'], $statuses['status']['constraints']['values']);
    }

    public function test_schema_response_is_valid_json(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->jsonApi('get', '/api/assistants/schema');

        $response->assertOk();
        $this->assertJson($response->getContent());
    }
}
