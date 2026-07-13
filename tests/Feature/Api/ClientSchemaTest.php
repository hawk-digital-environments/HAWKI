<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Assistants\AssistantFeedback;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\AssistantTag;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class ClientSchemaTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private Assistant $assistant;
    private AssistantCategory $category;
    private AssistantTag $tag1;
    private AssistantTag $tag2;
    private AssistantSetting $settingLang;
    private AssistantSetting $settingFormality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->category = AssistantCategory::factory()->create(['text' => 'research']);

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
            'release_stage' => AssistantReleaseStage::DRAFT->value,
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temp' => 0.7,
            'top_p' => 0.95,
            'creator_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $this->tag1 = AssistantTag::create(['text' => 'php']);
        $this->tag2 = AssistantTag::create(['text' => 'javascript']);
        $this->assistant->assistantTags()->attach([$this->tag1->id, $this->tag2->id]);

        AssistantSettingValue::create([
            'assistant_id' => $this->assistant->id,
            'setting_id' => $this->settingLang->id,
            'value' => 'en',
        ]);

        AssistantFeedback::create([
            'assistant_id' => $this->assistant->id,
            'user_id' => $this->user->id,
            'text' => 'Great assistant!',
        ]);

        AssistantReview::create([
            'assistant_id' => $this->assistant->id,
            'status' => 'approved',
            'reason' => null,
        ]);
    }

    public function testSchemaRequiresAuthentication(): void
    {
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema')
            ->assertStatus(401);
    }

    public function testSchemaReturnsAllResourceTypes(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');

        $response->assertOk();

        $resources = $response->json('resources');

        $expectedTypes = [
            'assistants', 'assistant-avatars', 'assistant-categories',
            'assistant-settings', 'assistant-setting-values', 'users',
            'assistant-tags', 'assistant-user-prompts', 'ai-tools', 'mcp-servers',
            'ai-models', 'ai-providers',
            'assistant-reviews', 'assistant-versions', 'organizations', 'assistant-feedback',
        ];

        foreach ($expectedTypes as $type) {
            self::assertArrayHasKey($type, $resources, "Missing resource type: {$type}");
        }

        self::assertSame('1.0', $response->json('version'));
        self::assertNotNull($response->json('generatedAt'));
    }

    public function testSchemaTopLevelStructure(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');

        $response->assertOk()->assertJsonStructure([
            'version',
            'generatedAt',
            'resources',
        ]);
    }

    public function testAssistantResourceStructure(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $a = $response->json('resources.assistants');

        self::assertSame('assistants', $a['type']);
        self::assertSame('Assistants', $a['displayName']);

        $requiredKeys = ['type', 'displayName', 'endpoints',
            'attributes', 'relationships', 'actions', 'filters', 'sortable', 'includable'];

        foreach ($requiredKeys as $key) {
            self::assertArrayHasKey($key, $a, "Missing key: {$key}");
        }
    }

    public function testAssistantEndpoints(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $endpoints = $response->json('resources.assistants.endpoints');

        $expected = ['list', 'create', 'read', 'update', 'delete'];

        foreach ($expected as $name) {
            self::assertArrayHasKey($name, $endpoints);
            self::assertArrayHasKey('method', $endpoints[$name]);
            self::assertArrayHasKey('url', $endpoints[$name]);
        }

        self::assertSame('GET', $endpoints['list']['method']);
        self::assertSame('/api/hawki/v1/assistants', $endpoints['list']['url']);
        self::assertTrue($endpoints['list']['allowed']);
        self::assertSame('POST', $endpoints['create']['method']);
        self::assertSame('PATCH', $endpoints['update']['method']);
        self::assertSame('/api/hawki/v1/assistants/{id}', $endpoints['update']['url']);
        self::assertSame('DELETE', $endpoints['delete']['method']);
        self::assertTrue($endpoints['update']['allowed']);
        self::assertTrue($endpoints['delete']['allowed']);
        self::assertTrue($endpoints['read']['allowed']);
    }

    public function testAssistantAttributesExist(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        $expectedFields = [
            'name', 'handle', 'system_prompt', 'greeting', 'description',
            'detail_description', 'allow_remix', 'allow_model_select',
            'release_stage', 'model', 'max_tokens', 'temp', 'top_p',
            'created_at', 'updated_at', 'is_favorite',
        ];

        foreach ($expectedFields as $field) {
            self::assertArrayHasKey($field, $attrs, "Missing attribute: {$field}");
        }

        // version_text must NOT appear (hidden field)
        self::assertArrayNotHasKey('version_text', $attrs, 'Hidden field version_text must not appear');

        // Every attribute must have a type
        foreach ($attrs as $name => $attr) {
            self::assertArrayHasKey('type', $attr, "Attribute '{$name}' missing type");
        }
    }

    public function testAttributeTypes(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        self::assertSame('string', $attrs['name']['type']);
        self::assertSame('string', $attrs['handle']['type']);
        self::assertSame('string', $attrs['system_prompt']['type']);
        self::assertSame('boolean', $attrs['allow_remix']['type']);
        self::assertSame('boolean', $attrs['allow_model_select']['type']);
        self::assertSame('enum', $attrs['release_stage']['type']);
        self::assertSame('number', $attrs['max_tokens']['type']);
        self::assertSame('number', $attrs['temp']['type']);
        self::assertSame('number', $attrs['top_p']['type']);
        self::assertSame('datetime', $attrs['created_at']['type']);
        self::assertSame('datetime', $attrs['updated_at']['type']);
        self::assertSame('boolean', $attrs['is_favorite']['type']);
    }

    public function testReadonlyAttributes(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        self::assertTrue($attrs['created_at']['readOnly']);
        self::assertTrue($attrs['updated_at']['readOnly']);
        self::assertTrue($attrs['is_favorite']['readOnly']);
        self::assertTrue($attrs['release_stage']['readOnly']);

        // Writable fields must NOT be readOnly
        self::assertArrayNotHasKey('readOnly', $attrs['name']);
        self::assertArrayNotHasKey('readOnly', $attrs['system_prompt']);
    }

    public function testAttributeConstraints(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        // String constraint
        self::assertSame(255, $attrs['name']['constraints']['maxLength']);

        // Enum constraint
        self::assertSame(
            ['draft', 'private', 'organizational', 'federated'],
            $attrs['release_stage']['constraints']['values'],
        );

        // Numeric constraints
        self::assertSame(0, $attrs['max_tokens']['constraints']['minimum']);
        self::assertTrue($attrs['max_tokens']['constraints']['integer']);

        self::assertSame(0, $attrs['temp']['constraints']['minimum']);
        self::assertSame(1, $attrs['temp']['constraints']['maximum']);

        self::assertSame(0, $attrs['top_p']['constraints']['minimum']);
        self::assertSame(1, $attrs['top_p']['constraints']['maximum']);
    }

    public function testWritableOnResourceAttributes(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        // Writable via main resource endpoint
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['name']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['handle']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['system_prompt']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['greeting']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['description']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['detail_description']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['allow_remix']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['allow_model_select']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['model']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['max_tokens']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['temp']['writable_on']);
        self::assertSame([['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']], $attrs['top_p']['writable_on']);
    }

    public function testWritableOnActionOnlyAttributes(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        // is_favorite is computed (readOnly), managed via self-service actions.
        self::assertSame(
            [
                ['method' => 'POST', 'path' => '/api/hawki/v1/assistants/{id}/actions/favorite'],
                ['method' => 'DELETE', 'path' => '/api/hawki/v1/assistants/{id}/actions/favorite'],
            ],
            $attrs['is_favorite']['writable_on'],
        );

        // release_stage is readOnly on the resource and only changeable via the
        // dedicated release action (not via PATCH).
        self::assertSame(
            [['method' => 'POST', 'path' => '/api/hawki/v1/assistants/{id}/actions/release']],
            $attrs['release_stage']['writable_on'],
        );
    }

    public function testReadonlyAttributesHaveNoWritableOn(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $attrs = $response->json('resources.assistants.attributes');

        self::assertArrayNotHasKey('writable_on', $attrs['created_at']);
        self::assertArrayNotHasKey('writable_on', $attrs['updated_at']);
    }

    public function testRelationshipsExist(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        $expected = [
            'assistant_category', 'assistant_avatar', 'assistant_setting_values', 'assistant_user_prompts', 'ai_tools',
            'assistant_tags', 'creator', 'remix_creator', 'remixed_assistant',
            'assistant_versions', 'organization', 'assistant_review', 'assistant_feedback', 'shared_users',
        ];

        foreach ($expected as $rel) {
            self::assertArrayHasKey($rel, $rels, "Missing relationship: {$rel}");
        }

        self::assertCount(14, $rels);
    }

    public function testRelationshipCardinality(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        self::assertSame('toOne', $rels['assistant_category']['cardinality']);
        self::assertSame('toOne', $rels['assistant_avatar']['cardinality']);
        self::assertSame('toOne', $rels['creator']['cardinality']);
        self::assertSame('toOne', $rels['assistant_review']['cardinality']);
        self::assertSame('toMany', $rels['assistant_tags']['cardinality']);
        self::assertSame('toMany', $rels['assistant_versions']['cardinality']);
        self::assertSame('toMany', $rels['assistant_feedback']['cardinality']);
        self::assertSame('toMany', $rels['assistant_setting_values']['cardinality']);
    }

    public function testRelationshipTypes(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        self::assertSame('assistant-categories', $rels['assistant_category']['type']);
        self::assertSame('assistant-avatars', $rels['assistant_avatar']['type']);
        self::assertSame('assistant-setting-values', $rels['assistant_setting_values']['type']);
        self::assertSame('users', $rels['creator']['type']);
        self::assertSame('assistant-tags', $rels['assistant_tags']['type']);
        self::assertSame('assistant-reviews', $rels['assistant_review']['type']);
    }

    public function testRelationshipWritableOn(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        $patch = [['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}']];

        // Writable via main resource endpoint
        // Writable via main resource endpoint + relationship endpoint (to-one: PATCH only)
        self::assertSame([
            ['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}'],
            ['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}/relationships/assistant-category'],
        ], $rels['assistant_category']['writable_on']);

        self::assertSame([
            ['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}'],
            ['method' => 'POST', 'path' => '/api/hawki/v1/assistants/{id}/relationships/ai-tools'],
            ['method' => 'DELETE', 'path' => '/api/hawki/v1/assistants/{id}/relationships/ai-tools'],
            ['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}/relationships/ai-tools'],
        ], $rels['ai_tools']['writable_on']);

        self::assertSame([
            ['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}'],
            ['method' => 'POST', 'path' => '/api/hawki/v1/assistants/{id}/relationships/assistant-tags'],
            ['method' => 'DELETE', 'path' => '/api/hawki/v1/assistants/{id}/relationships/assistant-tags'],
            ['method' => 'PATCH', 'path' => '/api/hawki/v1/assistants/{id}/relationships/assistant-tags'],
        ], $rels['assistant_tags']['writable_on']);

        // ReadOnly relationships should NOT have writable_on
        self::assertArrayNotHasKey('writable_on', $rels['creator']);
        self::assertArrayNotHasKey('writable_on', $rels['assistant_versions']);
        self::assertArrayNotHasKey('writable_on', $rels['organization']);
        self::assertArrayNotHasKey('writable_on', $rels['remix_creator']);
        self::assertArrayNotHasKey('writable_on', $rels['remixed_assistant']);
        self::assertArrayNotHasKey('writable_on', $rels['assistant_user_prompts']);
        self::assertArrayNotHasKey('writable_on', $rels['assistant_feedback']);
        self::assertArrayNotHasKey('writable_on', $rels['assistant_setting_values']);
        self::assertArrayNotHasKey('writable_on', $rels['assistant_review']);
    }

    public function testRelationshipEndpoints(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $rels = $response->json('resources.assistants.relationships');

        // Every relationship must have a fetch endpoint
        foreach ($rels as $name => $rel) {
            self::assertArrayHasKey('fetch', $rel['endpoints'], "Relationship '{$name}' missing fetch endpoint");
            self::assertSame('GET', $rel['endpoints']['fetch']['method']);
            self::assertStringContainsString("{id}/{$name}", $rel['endpoints']['fetch']['url']);
        }
    }

    public function testActionsExist(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        $expectedActions = ['remix', 'favorite'];

        foreach ($expectedActions as $name) {
            self::assertArrayHasKey($name, $actions, "Missing action: {$name}");
        }
    }

    public function testActionStructure(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        foreach ($actions as $name => $action) {
            self::assertArrayHasKey('method', $action);
            self::assertArrayHasKey('url', $action);
            self::assertArrayHasKey('allowed', $action);
            self::assertContains($action['method'], ['POST', 'DELETE']);
            self::assertTrue($action['allowed'], "Action '{$name}' should be allowed for authenticated user");
        }
    }

    public function testActionUrls(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        self::assertSame('/api/hawki/v1/assistants/{id}/actions/remix', $actions['remix']['url']);
        self::assertSame('/api/hawki/v1/assistants/{id}/actions/favorite', $actions['favorite']['url']);
    }

    public function testActionInputSchemas(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $actions = $response->json('resources.assistants.actions');

        // remix has no FormRequest → no input schema
        self::assertArrayNotHasKey('input', $actions['remix']);
    }

    public function testFilters(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $filters = $response->json('resources.assistants.filters');

        self::assertIsArray($filters);
        self::assertNotEmpty($filters);

        $filterNames = array_column($filters, 'name');
        self::assertContains('filter[assistant_category]', $filterNames);
        self::assertContains('filter[name]', $filterNames);
        self::assertContains('filter[is_favorite]', $filterNames);
        self::assertContains('filter[release_stage]', $filterNames);
    }

    public function testSortable(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $sortable = $response->json('resources.assistants.sortable');

        self::assertContains('id', $sortable);
        self::assertContains('created_at', $sortable);
        self::assertContains('updated_at', $sortable);
    }

    public function testIncludable(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $includable = $response->json('resources.assistants.includable');

        self::assertContains('assistant_category', $includable);
        self::assertContains('assistant_tags', $includable);
        self::assertContains('creator', $includable);
        self::assertContains('assistant_versions', $includable);
        self::assertContains('assistant_setting_values', $includable);
        self::assertContains('assistant_review', $includable);
        self::assertContains('assistant_feedback', $includable);
    }

    public function testAuthenticatedEndpointAllowed(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $endpoints = $response->json('resources.assistants.endpoints');

        self::assertTrue($endpoints['create']['allowed']);
        self::assertTrue($endpoints['list']['allowed']);
        self::assertTrue($endpoints['read']['allowed']);
        self::assertTrue($endpoints['update']['allowed']);
        self::assertTrue($endpoints['delete']['allowed']);
    }

    public function testUnauthenticatedActionsNotAllowed(): void
    {
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema')
            ->assertStatus(401);
    }

    public function testNonAuthorizableResourcesHaveAllEndpointsAllowed(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $resources = $response->json('resources');

        // Tags has no authorization (authorizable() returns false); now only index/show.
        self::assertTrue($resources['assistant-tags']['endpoints']['list']['allowed']);

        // Categories has only index/show (no create endpoint)
        self::assertTrue($resources['assistant-categories']['endpoints']['list']['allowed']);
    }

    public function testOtherResourceTypesHaveBasicStructure(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $resources = $response->json('resources');

        // Resources without standalone CRUD routes (relationship-only).
        $relationOnly = ['assistant-versions', 'organizations', 'attachments'];

        foreach ($resources as $type => $resource) {
            self::assertArrayHasKey('type', $resource, "Type '{$type}' missing type field");
            self::assertArrayHasKey('attributes', $resource);
            self::assertArrayHasKey('filters', $resource);
            self::assertArrayHasKey('sortable', $resource);
            self::assertArrayHasKey('includable', $resource);

            self::assertSame($type, $resource['type']);

            if (\in_array($type, $relationOnly, true)) {
                self::assertArrayNotHasKey('endpoints', $resource, "Type '{$type}' should not have standalone endpoints");
            } else {
                self::assertArrayHasKey('endpoints', $resource, "Type '{$type}' missing endpoints");
            }
        }
    }

    public function testRelationshipOnlyResourcesHaveNoStandaloneEndpoints(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $resources = $response->json('resources');

        $relationOnly = ['assistant-versions', 'organizations', 'attachments'];

        foreach ($relationOnly as $type) {
            self::assertArrayHasKey($type, $resources, "Missing resource: {$type}");
            self::assertArrayNotHasKey(
                'endpoints',
                $resources[$type],
                "'{$type}' should not have standalone endpoints",
            );
            self::assertSame($type, $resources[$type]['type']);
            self::assertArrayHasKey('attributes', $resources[$type]);
            self::assertArrayHasKey('filters', $resources[$type]);
            self::assertArrayHasKey('sortable', $resources[$type]);
            self::assertArrayHasKey('includable', $resources[$type]);
        }
    }

    public function testEnumsOnOtherResources(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');
        $resources = $response->json('resources');

        // ai-model-statuses was removed in v2.5 — verify it's absent
        self::assertArrayNotHasKey('ai-model-statuses', $resources);
    }

    public function testSchemaResponseIsValidJson(): void
    {
        $this->actingAsUser($this->user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants/schema');

        $response->assertOk();
        self::assertJson($response->getContent());
    }
}
