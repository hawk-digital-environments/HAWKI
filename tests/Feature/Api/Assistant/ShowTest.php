<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Category;
use App\Models\Assistants\Language;
use App\Models\Organization;
use App\Models\User;
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

        $this->jsonApi('get',"/api/assistants/{$assistant->id}")
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
                        'formality' => $assistant->formality,
                        'model' => $assistant->model,
                        'model_length' => $assistant->model_length,
                        'model_temp' => $assistant->model_temp,
                        'model_top_p' => $assistant->model_top_p,
                        'created_at' => $assistant->created_at->toJson(),
                        'updated_at' => $assistant->updated_at->toJson(),
                    ],
                    'links' => [
                        'self' => config('app.url') . "/api/assistants/{$assistant->id}",
                        'remix' => [
                            'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/remix",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                        'release' => [
                            'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/release",
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

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=creator,user_prompts,ai_tools,tags")
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
                        'user_prompts' => [
                            'data' => [],
                        ],
                        'ai_tools' => [
                            'data' => [],
                        ],
                        'tags' => [
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

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=organization")
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

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=versions")
            ->assertOk();

        $included = collect($response->json('included'));
        $versionResource = $included->first(fn ($item) => $item['type'] === 'versions');
        $this->assertEquals('Initial version', $versionResource['attributes']['text']);
        $this->assertEquals('1.0', $versionResource['attributes']['version']);
    }

    public function test_can_show_assistant_with_language(): void
    {
        $user = User::factory()->create();
        $language = Language::factory()->create(['text' => 'en']);
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'language_id' => $language->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=language")
            ->assertOk();

        $included = collect($response->json('included'));
        $langResource = $included->first(fn ($item) => $item['type'] === 'assistant-languages');
        $this->assertEquals('en', $langResource['attributes']['text']);
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

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=category")
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

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=creator")
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

        $response = $this->jsonApi('get',"/api/assistants/{$assistant->id}?include=remix_creator")
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

        $response = $this->jsonApi('get',"/api/assistants/{$remix->id}?include=remixed_assistant")
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

        $this->jsonApi('get',"/api/assistants/{$assistant->id}")
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

        $this->jsonApi('get',"/api/assistants/{$assistant->id}")
            ->assertForbidden();
    }

    public function test_show_assistant_shows_denied_release_link_for_non_owner(): void
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
                        'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/remix",
                        'meta' => ['message' => 'ALLOWED'],
                    ],
                    'release' => [
                        'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/release",
                        'meta' => ['message' => 'DENIED'],
                    ],
                ],
            ],
        ]);
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
                        'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/remix",
                        'meta' => ['message' => 'DENIED'],
                    ],
                    'release' => [
                        'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/release",
                        'meta' => ['message' => 'DENIED'],
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
}
