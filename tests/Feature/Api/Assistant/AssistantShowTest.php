<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AssistantSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantShowTest extends TestCase
{
    use RefreshDatabase;

    public function testCanShowAssistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id, 'allow_remix' => true]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
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
                        'self' => config('app.url') . "/api/hawki/v1/assistants/{$assistant->id}",
                        'remix' => [
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                    ],
                ],
            ]);
    }

    public function testCanShowAssistantWithRelations(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=creator,assistant_user_prompts,ai_tools,assistant_tags")
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
        $creatorResource = $included->first(static fn ($item) => 'users' === $item['type']);
        self::assertEquals((string) $user->id, $creatorResource['id']);
        self::assertEquals($user->name, $creatorResource['attributes']['display_name']);
    }

    public function testCanShowAssistantWithOrganization(): void
    {
        $org = Organization::first();

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=organization")
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
        $orgResource = $included->first(static fn ($item) => 'organizations' === $item['type']);
        self::assertEquals($org->name, $orgResource['attributes']['name']);
    }

    public function testCanShowAssistantWithVersions(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_versions")
            ->assertOk();

        $included = collect($response->json('included'));
        $versionResource = $included->first(static fn ($item) => 'assistant-versions' === $item['type']);
        self::assertEquals('{"changes":[]}', $versionResource['attributes']['text']);
        self::assertEquals('1.0', $versionResource['attributes']['version']);
    }

    public function testCanShowAssistantWithSettingValues(): void
    {
        $this->seed(AssistantSettingSeeder::class);
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

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_setting_values")
            ->assertOk();

        $included = collect($response->json('included'));
        $valueResource = $included->first(static fn ($item) => 'assistant-setting-values' === $item['type']);
        self::assertEquals('en', $valueResource['attributes']['value']);
    }

    public function testCanShowAssistantWithNestedSettingInclude(): void
    {
        $this->seed(AssistantSettingSeeder::class);
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

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_setting_values.setting")
            ->assertOk();

        $included = collect($response->json('included'));
        $valueResource = $included->first(static fn ($item) => 'assistant-setting-values' === $item['type']);
        self::assertNotNull($valueResource, 'Setting value resource should be included');
        self::assertSame((string) $setting->id, $valueResource['relationships']['setting']['data']['id']);

        $settingResource = $included->first(static fn ($item) => 'assistant-settings' === $item['type']);
        self::assertNotNull($settingResource, 'Related setting resource should be included via nested include');
        self::assertSame('language', $settingResource['attributes']['key']);
    }

    public function testCanShowAssistantWithCategory(): void
    {
        $user = User::factory()->create();
        $category = AssistantCategory::factory()->create(['text' => 'general']);
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_category")
            ->assertOk();

        $included = collect($response->json('included'));
        $catResource = $included->first(static fn ($item) => 'assistant-categories' === $item['type']);
        self::assertEquals('general', $catResource['attributes']['text']);
    }

    public function testCanShowAssistantWithCreator(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=creator")
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
        $creatorResource = $included->first(static fn ($item) => 'users' === $item['type']);
        self::assertEquals($user->name, $creatorResource['attributes']['display_name']);
    }

    public function testCanShowAssistantWithRemixedCreator(): void
    {
        $originalCreator = User::factory()->create();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'remixed_creator_id' => $originalCreator->id,
        ]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=remix_creator")
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
        $creatorResource = $included->first(static fn ($item) => 'users' === $item['type']);
        self::assertEquals($originalCreator->name, $creatorResource['attributes']['display_name']);
    }

    public function testCanShowAssistantWithRemixedAssistant(): void
    {
        $user = User::factory()->create();
        $original = Assistant::factory()->create(['creator_id' => $user->id]);
        $remix = Assistant::factory()->create([
            'creator_id' => $user->id,
            'remixed_assistant_id' => $original->id,
        ]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$remix->id}?include=remixed_assistant")
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
        $originalResource = $included->first(static fn ($item) => 'assistants' === $item['type']);
        self::assertEquals($original->name, $originalResource['attributes']['name']);
    }

    public function testRelationshipDataAbsentWhenNotIncluded(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonMissingPath('included');
    }

    public function testUserCannotShowOtherUsersPrivateAssistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function testUserCannotShowOtherUsersDraftAssistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function testShowAssistantShowsDeniedReleaseLinkForNonCreator(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'allow_remix' => true,
        ]);

        $this->actingAsUser($other);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'links' => [
                    'remix' => [
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                    'release' => [
                        'meta' => ['message' => 'DENIED'],
                    ],
                ],
            ],
        ]);
    }

    public function testShowAssistantShowsDeniedRemixLinkWhenNotAllowed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'allow_remix' => false,
        ]);

        $this->actingAsUser($other);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'links' => [
                    'remix' => [
                        'meta' => ['message' => 'DENIED'],
                    ],
                ],
            ],
        ]);
    }

    public function testShowAssistantShowsAllowedFavoriteLinksForCreator(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'draft',
        ]);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk();

        // Favorite actions authorize via view (delegated from addFavorite/removeFavorite
        // policy methods), so the creator must see both as ALLOWED - matching the
        // authorization the controller actually enforces. Both share the same route
        // URI (/favorite), distinguished by HTTP method, so the href must be
        // the real registered route, not derived from the controller method name.
        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'links' => [
                    'addFavorite' => [
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                    'removeFavorite' => [
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                ],
            ],
        ]);
    }

    public function testShowAssistantHasNoActionLinksWhenUnauthenticated(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'federated',
        ]);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertStatus(401);
    }

    public function testIsFavoriteAttributeOnShowWhenFavorited(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $owner->favoriteAssistants()->attach($assistant->id);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.is_favorite', true);
    }

    public function testIsFavoriteAttributeOnShowWhenNotFavorited(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.is_favorite', false);
    }
}
