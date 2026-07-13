<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Database\Seeders\AssistantSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantIndexTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCannotListAssistants(): void
    {
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function testCanListAssistants(): void
    {
        $user = User::factory()->create();
        $assistants = Assistant::factory()->count(3)->create(['creator_id' => $user->id, 'allow_remix' => true]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
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
                                'allow_remix' => $assistant->allow_remix,
                                'allow_model_select' => $assistant->allow_model_select,
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
                                    'href' => config('app.url') . "/api/hawki/v1/assistants/{$assistant->id}/actions/remix",
                                    'meta' => ['message' => 'ALLOWED'],
                                ],
                            ],
                        ],
                    ],
                ]);
        }
    }

    public function testCanListAssistantsWithRelations(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=creator,assistant_user_prompts')
            ->assertOk();

        $response->assertJsonPath('data.0.relationships.creator.data.id', (string) $user->id);
        $response->assertJsonPath('data.0.relationships.creator.data.type', 'users');
        $response->assertJsonPath('data.0.relationships.assistant_user_prompts.data', []);

        $included = collect($response->json('included'));
        $creatorResource = $included->first(static fn ($item) => 'users' === $item['type']);
        self::assertEquals($user->name, $creatorResource['attributes']['display_name']);
    }

    public function testCanListAssistantsWithVersions(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=assistant_versions')
            ->assertOk();

        $included = collect($response->json('included'));
        $versionResource = $included->first(static fn ($item) => 'assistant-versions' === $item['type']);
        self::assertEquals('{"changes":[]}', $versionResource['attributes']['text']);
        self::assertEquals('1.0', $versionResource['attributes']['version']);
    }

    public function testCanFilterAssistantsByCategory(): void
    {
        $user = User::factory()->create();
        $education = AssistantCategory::factory()->create(['text' => 'education']);
        $general = AssistantCategory::factory()->create(['text' => 'general']);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $education->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $general->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $education->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?filter[assistant_category][text]=education&include=assistant_category')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $included = collect($response->json('included'));
        $catResources = $included->filter(static fn ($item) => 'assistant-categories' === $item['type']);

        foreach ($catResources as $catResource) {
            self::assertEquals('education', $catResource['attributes']['text']);
        }
    }

    public function testCanFilterAssistantsByMultipleCategories(): void
    {
        $user = User::factory()->create();
        $education = AssistantCategory::factory()->create(['text' => 'education']);
        $general = AssistantCategory::factory()->create(['text' => 'general']);
        $science = AssistantCategory::factory()->create(['text' => 'science']);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $education->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $general->id]);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $science->id]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?filter[assistant_category][text]=education,general&include=assistant_category')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $included = collect($response->json('included'));
        $catResources = $included->filter(static fn ($item) => 'assistant-categories' === $item['type']);

        foreach ($catResources as $catResource) {
            self::assertContains($catResource['attributes']['text'], ['education', 'general']);
        }
    }

    public function testFilterByCategoryReturnsEmptyWhenNoMatch(): void
    {
        $user = User::factory()->create();
        $general = AssistantCategory::factory()->create(['text' => 'general']);
        Assistant::factory()->create(['creator_id' => $user->id, 'category_id' => $general->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?filter[assistant_category][text]=nonexistent')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testListWithoutCategoryFilterReturnsAll(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->count(3)->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function testCanListAssistantsWithOrganization(): void
    {
        $org = Organization::first();

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=organization')
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
        $orgResource = $included->first(static fn ($item) => 'organizations' === $item['type']);
        self::assertEquals($org->name, $orgResource['attributes']['name']);
    }

    public function testCanListAssistantsWithSettingValuesAndCategory(): void
    {
        $this->seed(AssistantSettingSeeder::class);
        $setting = AssistantSetting::where('key', 'language')->firstOrFail();
        $category = AssistantCategory::factory()->create(['text' => 'education']);
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'category_id' => $category->id,
        ]);
        AssistantSettingValue::create([
            'assistant_id' => $assistant->id,
            'setting_id' => $setting->id,
            'value' => 'de',
        ]);

        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=assistant_setting_values,assistant_category')
            ->assertOk();

        $response->assertJson([
            'data' => [
                [
                    'id' => (string) $assistant->id,
                    'relationships' => [
                        'assistant_setting_values' => [
                            'data' => [
                                [
                                    'id' => (string) $assistant->settingValues->first()->id,
                                    'type' => 'assistant-setting-values',
                                ],
                            ],
                        ],
                        'assistant_category' => [
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
        $valueResource = $included->first(static fn ($item) => 'assistant-setting-values' === $item['type']);
        self::assertEquals('de', $valueResource['attributes']['value']);
        $catResource = $included->first(static fn ($item) => 'assistant-categories' === $item['type']);
        self::assertEquals('education', $catResource['attributes']['text']);
    }

    public function testUserCannotSeeOtherUsersPrivateAssistantInList(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testUserCannotSeeOtherUsersDraftAssistantInList(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testUserCanSeeOtherUsersOrganizationalAssistantInList(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $assistant->id);
    }

    public function testUserCanSeeOwnPrivateAssistantInList(): void
    {
        $user = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function testPaginationLinksPreserveQueryParams(): void
    {
        $user = User::factory()->create();
        $general = AssistantCategory::factory()->create(['text' => 'general']);
        AssistantCategory::factory()->create(['text' => 'education']);
        Assistant::factory()->count(20)->create([
            'creator_id' => $user->id,
            'category_id' => $general->id,
        ]);

        $this->actingAsUser($user);

        $query = http_build_query([
            'include' => 'assistant_tags,assistant_category',
            'fields' => ['assistant-tags' => 'text'],
            'filter' => ['assistant_category' => ['text' => 'general']],
            'page' => ['size' => 5],
        ]);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants?{$query}")
            ->assertOk()
            ->assertJsonStructure([
                'links' => ['first', 'last', 'next'],
            ]);

        $links = $response->json('links');

        self::assertStringContainsString('include=assistant_tags%2Cassistant_category', $links['first']);
        self::assertStringContainsString('fields%5Bassistant-tags%5D=text', $links['first']);
        self::assertStringContainsString('filter%5Bassistant_category%5D%5Btext%5D=general', $links['first']);
        self::assertStringContainsString('page%5Bsize%5D=5', $links['first']);
        self::assertStringContainsString('page%5Bnumber%5D=1', $links['first']);
        self::assertStringContainsString('page%5Bnumber%5D=2', $links['next']);
    }

    public function testPaginationAdvancesPagesCorrectly(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->count(2)->create([
            'creator_id' => $user->id,
            'release_stage' => 'federated',
        ]);

        $this->actingAsUser($user);

        $page1Query = http_build_query(['page' => ['size' => 1]]);

        $page1 = $this->jsonApiRaw('get', "/api/hawki/v1/assistants?{$page1Query}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        self::assertStringContainsString('page%5Bnumber%5D=2', $page1->json('links.next'));
        self::assertNull($page1->json('links.prev'));

        $page2Query = http_build_query(['page' => ['size' => 1, 'number' => 2]]);

        $page2 = $this->jsonApiRaw('get', "/api/hawki/v1/assistants?{$page2Query}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        self::assertStringContainsString('page%5Bnumber%5D=1', $page2->json('links.prev'));
        self::assertNull($page2->json('links.next'));
    }

    public function testUserCanSeeFederatedAssistantInList(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function testListShowsDeniedActionLinksForNonOwner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'allow_remix' => false,
        ]);

        $this->actingAsUser($other);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJson([
            'data' => [
                [
                    'id' => (string) $assistant->id,
                    'links' => [
                        'remix' => [
                            'href' => config('app.url') . "/api/hawki/v1/assistants/{$assistant->id}/actions/remix",
                            'meta' => ['message' => 'DENIED'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanFilterAssistantsByNameCaseInsensitive(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Helper']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Writing Assistant']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Reviewer']);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['name' => 'code']]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testFilterByNameIsCaseInsensitive(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Helper']);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['name' => 'CODE']]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function testFilterByNameReturnsEmptyWhenNoMatch(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Code Helper']);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['name' => 'nonexistent']]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testIsFavoriteAttributeReturnedInList(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $user->favoriteAssistants()->attach($assistant->id);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.is_favorite', true);
    }

    public function testIsFavoriteIsFalseWhenNotFavorited(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.is_favorite', false);
    }

    public function testCanFilterAssistantsByIsFavoriteTrue(): void
    {
        $user = User::factory()->create();
        $favorited = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Favorited']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Not Favorited']);
        $user->favoriteAssistants()->attach($favorited->id);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['is_favorite' => 'true']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'Favorited');
    }

    public function testCanFilterAssistantsByIsFavoriteFalse(): void
    {
        $user = User::factory()->create();
        $favorited = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Favorited']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Not Favorited A']);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Not Favorited B']);
        $user->favoriteAssistants()->attach($favorited->id);

        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['is_favorite' => 'false']]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testCanFilterAssistantReleaseStatus(): void
    {
        $user = User::factory()->create();
        $draftAssistant = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Release draft', 'release_stage' => AssistantReleaseStage::DRAFT]);
        Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Release organizational', 'release_stage' => AssistantReleaseStage::ORGANIZATIONAL]);
        $privateAssistant = Assistant::factory()->create(['creator_id' => $user->id, 'name' => 'Release private', 'release_stage' => AssistantReleaseStage::PRIVATE]);

        $this->actingAsUser($user);

        // Single value (string) is accepted via the comma delimiter.
        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['release_stage' => AssistantReleaseStage::DRAFT]]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $draftAssistant->id);

        // Multiple comma-separated values are accepted.
        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['release_stage' => AssistantReleaseStage::DRAFT->value . ',' . AssistantReleaseStage::PRIVATE->value]]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->sort()->values();
        self::assertSame([(string) $draftAssistant->id, (string) $privateAssistant->id], $ids->all());
    }

    public function testIsFavoriteFilterOnlyScopesToAuthenticatedUser(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $userA->id,
            'release_stage' => 'organizational',
        ]);
        $userA->favoriteAssistants()->attach($assistant->id);

        $this->actingAsUser($userB);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistants?' . http_build_query(['filter' => ['is_favorite' => 'true']]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
