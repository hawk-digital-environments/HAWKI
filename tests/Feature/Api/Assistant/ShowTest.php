<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Assistants\Category;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_assistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id, 'allow_remix' => true]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $assistant->id,
                    'type' => 'assistants',
                    'attributes' => [
                        'name' => $assistant->name,
                        'handle' => $assistant->handle,
                        'system_prompt' => $assistant->system_prompt,
                        'greeting' => $assistant->greeting,
                        'description' => $assistant->description,
                        'detail_description' => $assistant->detail_description,
                        'allow_remix' => (int) $assistant->allow_remix,
                        'allow_model_select' => (int) $assistant->allow_model_select,
                        'release_stage' => $assistant->release_stage,
                        'model' => $assistant->model,
                        'max_tokens' => $assistant->max_tokens,
                        'temp' => $assistant->temp,
                        'top_p' => $assistant->top_p,
                        'created_at' => $assistant->created_at->toJson(),
                        'updated_at' => $assistant->updated_at->toJson(),
                    ],
                    'links' => [
                        'self' => config('app.url')."/api/assistants/{$assistant->id}",
                        'remix' => [
                            'href' => config('app.url')."/api/assistants/{$assistant->id}/actions/remix",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_show_assistant_with_relations(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=creator,assistant_user_prompts,ai_tools,assistant_tags")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $assistant->id,
                    'type' => 'assistants',
                    'relationships' => [
                        'creator' => [
                            'data' => [
                                'id' => (string) $user->id,
                                'type' => 'users',
                            ],
                        ],
                        'assistant_user_prompts' => [
                            'data' => [],
                        ],
                        'ai_tools' => [
                            'data' => [],
                        ],
                        'assistant_tags' => [
                            'data' => [],
                        ],
                    ],
                ],
            ]);

        $included = collect($response->json('included'));
        $creatorResource = $included->first(fn ($item) => $item['type'] === 'users');
        $this->assertEquals((string) $user->id, $creatorResource['id']);
        $this->assertEquals($user->name, $creatorResource['attributes']['name']);
    }

    public function test_can_show_assistant_with_organization(): void
    {
        $org = Organization::first();

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $org->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=organization")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'relationships' => [
                    'organization' => [
                        'data' => [
                            'id' => (string) $org->id,
                            'type' => 'organizations',
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $orgResource = $included->first(fn ($item) => $item['type'] === 'organizations');
        $this->assertEquals($org->name, $orgResource['attributes']['name']);
    }

    public function test_can_show_assistant_with_versions(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=versions")
            ->assertOk();

        $included = collect($response->json('included'));
        $versionResource = $included->first(fn ($item) => $item['type'] === 'versions');
        $this->assertEquals('{"changes":[]}', $versionResource['attributes']['text']);
        $this->assertEquals('1.0', $versionResource['attributes']['version']);
    }

    public function test_can_show_assistant_with_setting_values(): void
    {
        $this->seed(SettingSeeder::class);
        $user = User::factory()->create();
        $setting = AssistantSetting::where('key', 'language')->firstOrFail();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
        ]);
        AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => 'en',
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_setting_values")
            ->assertOk();

        $included = collect($response->json('included'));
        $valueResource = $included->first(fn ($item) => $item['type'] === 'assistant-setting-values');
        $this->assertEquals('en', $valueResource['attributes']['value']);
    }

    public function test_can_show_assistant_with_nested_setting_include(): void
    {
        $this->seed(SettingSeeder::class);
        $user = User::factory()->create();
        $setting = AssistantSetting::where('key', 'language')->firstOrFail();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
        ]);
        AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => 'en',
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_setting_values.setting")
            ->assertOk();

        $included = collect($response->json('included'));
        $valueResource = $included->first(fn ($item) => $item['type'] === 'assistant-setting-values');
        $this->assertNotNull($valueResource, 'Setting value resource should be included');
        $this->assertSame((string) $setting->id, $valueResource['relationships']['setting']['data']['id']);

        $settingResource = $included->first(fn ($item) => $item['type'] === 'assistant-settings');
        $this->assertNotNull($settingResource, 'Related setting resource should be included via nested include');
        $this->assertSame('language', $settingResource['attributes']['key']);
    }

    public function test_can_show_assistant_with_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['text' => 'general']);
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=category")
            ->assertOk();

        $included = collect($response->json('included'));
        $catResource = $included->first(fn ($item) => $item['type'] === 'assistant-categories');
        $this->assertEquals('general', $catResource['attributes']['text']);
    }

    public function test_can_show_assistant_with_creator(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=creator")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'relationships' => [
                    'creator' => [
                        'data' => [
                            'id' => (string) $user->id,
                            'type' => 'users',
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $creatorResource = $included->first(fn ($item) => $item['type'] === 'users');
        $this->assertEquals($user->name, $creatorResource['attributes']['name']);
    }

    public function test_can_show_assistant_with_remixed_creator(): void
    {
        $originalCreator = User::factory()->create();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'remixed_creator_id' => $originalCreator->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=remix_creator")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'relationships' => [
                    'remix_creator' => [
                        'data' => [
                            'id' => (string) $originalCreator->id,
                            'type' => 'users',
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $creatorResource = $included->first(fn ($item) => $item['type'] === 'users');
        $this->assertEquals($originalCreator->name, $creatorResource['attributes']['name']);
    }

    public function test_can_show_assistant_with_remixed_assistant(): void
    {
        $user = User::factory()->create();
        $original = Assistant::factory()->create(['creator_id' => $user->id]);
        $remix = Assistant::factory()->create([
            'creator_id' => $user->id,
            'remixed_assistant_id' => $original->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$remix->id}?include=remixed_assistant")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $remix->id,
                'relationships' => [
                    'remixed_assistant' => [
                        'data' => [
                            'id' => (string) $original->id,
                            'type' => 'assistants',
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $originalResource = $included->first(fn ($item) => $item['type'] === 'assistants');
        $this->assertEquals($original->name, $originalResource['attributes']['name']);
    }

    public function test_relationship_data_absent_when_not_included(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonMissingPath('included');
    }

    public function test_user_cannot_show_other_users_private_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($other);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_show_other_users_draft_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        Sanctum::actingAs($other);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function test_show_assistant_shows_no_release_link_now_that_release_is_via_update(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'allow_remix' => true,
        ]);

        Sanctum::actingAs($other);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'links' => [
                    'remix' => [
                        'href' => config('app.url')."/api/assistants/{$assistant->id}/actions/remix",
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('release', $response->json('data.links'));
    }

    public function test_show_assistant_shows_denied_remix_link_when_not_allowed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'allow_remix' => false,
        ]);

        Sanctum::actingAs($other);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'links' => [
                    'remix' => [
                        'href' => config('app.url')."/api/assistants/{$assistant->id}/actions/remix",
                        'meta' => ['message' => 'DENIED'],
                    ],
                ],
            ],
        ]);
    }

    public function test_show_assistant_shows_allowed_favorite_links_for_creator(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk();

        // Favorite actions authorize via view (delegated from addFavorite/removeFavorite
        // policy methods), so the creator must see both as ALLOWED - matching the
        // authorization the controller actually enforces. Both share the same route
        // URI (/actions/favorite), distinguished by HTTP method, so the href must be
        // the real registered route, not derived from the controller method name.
        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'links' => [
                    'addFavorite' => [
                        'href' => config('app.url')."/api/assistants/{$assistant->id}/actions/favorite",
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                    'removeFavorite' => [
                        'href' => config('app.url')."/api/assistants/{$assistant->id}/actions/favorite",
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                ],
            ],
        ]);
    }

    public function test_show_assistant_has_no_action_links_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'federated',
        ]);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertUnauthorized();
    }

    public function test_is_favorite_attribute_on_show_when_favorited(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $owner->favoriteAssistants()->attach($assistant->id);

        Sanctum::actingAs($owner);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.is_favorite', true);
    }

    public function test_is_favorite_attribute_on_show_when_not_favorited(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.is_favorite', false);
    }
}
