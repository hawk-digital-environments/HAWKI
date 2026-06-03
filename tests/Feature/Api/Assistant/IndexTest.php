<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Category;
use App\Models\Assistants\Language;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_assistants(): void
    {
        $this->jsonApi('get', '/api/assistants')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_assistants(): void
    {
        $user = User::factory()->create();
        $assistants = Assistant::factory()->count(3)->create(['creator_id' => $user->id, 'allow_remix' => true]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($assistants as $i => $assistant) {
            $response
                ->assertJson([
                    'data' => [
                        $i => [
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
                                'feedback' => [
                                    'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/feedback",
                                    'meta' => ['message' => 'ALLOWED'],
                                ],
                            ],
                        ],
                    ],
                ]);
        }
    }

    public function test_can_list_assistants_with_relations(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants?include=creator,user_prompts')
            ->assertOk();

        $response->assertJsonPath('data.0.relationships.creator.data.id', (string) $user->id);
        $response->assertJsonPath('data.0.relationships.creator.data.type', 'users');
        $response->assertJsonPath('data.0.relationships.user_prompts.data', []);

        $included = collect($response->json('included'));
        $creatorResource = $included->first(fn($item) => $item['type'] === 'users');
        $this->assertEquals($user->name, $creatorResource['attributes']['name']);
    }

    public function test_can_list_assistants_with_versions(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants?include=versions')
            ->assertOk();

        $included = collect($response->json('included'));
        $versionResource = $included->first(fn($item) => $item['type'] === 'versions');
        $this->assertEquals('Initial version', $versionResource['attributes']['text']);
        $this->assertEquals('1.0', $versionResource['attributes']['version']);
    }

    public function test_can_filter_assistants_by_category(): void
    {
        $user = User::factory()->create();
        $education = Category::factory()->create(['text' => 'education']);
        $general = Category::factory()->create(['text' => 'general']);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $education->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $general->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $education->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants?filter[category][text]=education&include=category')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $included = collect($response->json('included'));
        $catResources = $included->filter(fn($item) => $item['type'] === 'assistant-categories');
        foreach ($catResources as $catResource) {
            $this->assertEquals('education', $catResource['attributes']['text']);
        }
    }

    public function test_can_filter_assistants_by_multiple_categories(): void
    {
        $user = User::factory()->create();
        $education = Category::factory()->create(['text' => 'education']);
        $general = Category::factory()->create(['text' => 'general']);
        $science = Category::factory()->create(['text' => 'science']);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $education->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $general->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $science->id]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants?filter[category][text]=education,general&include=category')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $included = collect($response->json('included'));
        $catResources = $included->filter(fn($item) => $item['type'] === 'assistant-categories');
        foreach ($catResources as $catResource) {
            $this->assertContains($catResource['attributes']['text'], ['education', 'general']);
        }
    }

    public function test_filter_by_category_returns_empty_when_no_match(): void
    {
        $user = User::factory()->create();
        $general = Category::factory()->create(['text' => 'general']);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $general->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?filter[category][text]=nonexistent')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_without_category_filter_returns_all(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->count(3)->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_list_assistants_with_organization(): void
    {
        $org = Organization::first();

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $org->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants?include=organization')
            ->assertOk();

        $response->assertJson([
            'data' => [
                [
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
            ],
        ]);

        $included = collect($response->json('included'));
        $orgResource = $included->first(fn($item) => $item['type'] === 'organizations');
        $this->assertEquals($org->name, $orgResource['attributes']['name']);
    }

    public function test_can_list_assistants_with_language_and_category(): void
    {
        $language = Language::factory()->create(['text' => 'de']);
        $category = Category::factory()->create(['text' => 'education']);
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'language_id' => $language->id,
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistants?include=language,category')
            ->assertOk();

        $response->assertJson([
            'data' => [
                [
                    'id' => (string) $assistant->id,
                    'relationships' => [
                        'language' => [
                            'data' => [
                                'id' => (string) $language->id,
                                'type' => 'assistant-languages',
                            ],
                        ],
                        'category' => [
                            'data' => [
                                'id' => (string) $category->id,
                                'type' => 'assistant-categories',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $langResource = $included->first(fn($item) => $item['type'] === 'assistant-languages');
        $this->assertEquals('de', $langResource['attributes']['text']);
        $catResource = $included->first(fn($item) => $item['type'] === 'assistant-categories');
        $this->assertEquals('education', $catResource['attributes']['text']);
    }

    public function test_user_cannot_see_other_users_private_assistant_in_list(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($other);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_see_own_private_assistant_in_list(): void
    {
        $user = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pagination_links_preserve_query_params(): void
    {
        $user = User::factory()->create();
        $general = Category::factory()->create(['text' => 'general']);
        Category::factory()->create(['text' => 'education']);
        Assistant::factory()->count(20)->create([
            'creator_id' => $user->id,
            'category_id' => $general->id,
        ]);

        Sanctum::actingAs($user);

        $query = http_build_query([
            'include' => 'tags,category',
            'fields' => ['tags' => 'text'],
            'filter' => ['category' => ['text' => 'general']],
            'page' => ['size' => 5],
        ]);

        $response = $this->jsonApi('get', "/api/assistants?{$query}")
            ->assertOk()
            ->assertJsonStructure([
                'links' => ['first', 'last', 'next'],
            ]);

        $links = $response->json('links');

        $this->assertStringContainsString('include=tags%2Ccategory', $links['first']);
        $this->assertStringContainsString('fields%5Btags%5D=text', $links['first']);
        $this->assertStringContainsString('filter%5Bcategory%5D%5Btext%5D=general', $links['first']);
        $this->assertStringContainsString('page%5Bsize%5D=5', $links['first']);
        $this->assertStringContainsString('page%5Bnumber%5D=1', $links['first']);
        $this->assertStringContainsString('page%5Bnumber%5D=2', $links['next']);
    }

    public function test_pagination_advances_pages_correctly(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->count(2)->create([
            'creator_id' => $user->id,
            'release_stage' => 'federated',
        ]);

        Sanctum::actingAs($user);

        $page1Query = http_build_query(['page' => ['size' => 1]]);

        $page1 = $this->jsonApi('get', "/api/assistants?{$page1Query}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertStringContainsString('page%5Bnumber%5D=2', $page1->json('links.next'));
        $this->assertNull($page1->json('links.prev'));

        $page2Query = http_build_query(['page' => ['size' => 1, 'number' => 2]]);

        $page2 = $this->jsonApi('get', "/api/assistants?{$page2Query}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertStringContainsString('page%5Bnumber%5D=1', $page2->json('links.prev'));
        $this->assertNull($page2->json('links.next'));
    }

    public function test_user_can_see_federated_assistant_in_list(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
        ]);

        Sanctum::actingAs($other);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_shows_denied_action_links_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'allow_remix' => false,
        ]);

        Sanctum::actingAs($other);

        $response = $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJson([
            'data' => [
                [
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
                        'feedback' => [
                            'href' => config('app.url') . "/api/assistants/{$assistant->id}/actions/feedback",
                            'meta' => ['message' => 'ALLOWED'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_can_filter_assistants_by_name_case_insensitive(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Helper']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Writing Assistant']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Reviewer']);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['name' => 'code']]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_by_name_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Helper']);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['name' => 'CODE']]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_name_returns_empty_when_no_match(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Helper']);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['name' => 'nonexistent']]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_is_favorite_attribute_returned_in_list(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $user->favoriteAssistants()->attach($assistant->id);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.is_favorite', true);
    }

    public function test_is_favorite_is_false_when_not_favorited(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.is_favorite', false);
    }

    public function test_can_filter_assistants_by_is_favorite_true(): void
    {
        $user = User::factory()->create();
        $favorited = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Favorited']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Not Favorited']);
        $user->favoriteAssistants()->attach($favorited->id);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['is_favorite' => 'true']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'Favorited');
    }

    public function test_can_filter_assistants_by_is_favorite_false(): void
    {
        $user = User::factory()->create();
        $favorited = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Favorited']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Not Favorited A']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Not Favorited B']);
        $user->favoriteAssistants()->attach($favorited->id);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['is_favorite' => 'false']]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_assistant_release_status(): void
    {
        $user = User::factory()->create();
        $draftAssistant = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Release draft', 'release_stage' => ReleaseStage::DRAFT]);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Release organizational', 'release_stage' => ReleaseStage::ORGANIZATIONAL]);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Release private', 'release_stage' => ReleaseStage::PRIVATE]);

        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['release_stage' => ReleaseStage::DRAFT]]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $draftAssistant -> id);
    }

    public function test_is_favorite_filter_only_scopes_to_authenticated_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $userA->id,
            'release_stage' => 'public',
        ]);
        $userA->favoriteAssistants()->attach($assistant->id);

        Sanctum::actingAs($userB);

        $this->jsonApi('get', '/api/assistants?' . http_build_query(['filter' => ['is_favorite' => 'true']]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
