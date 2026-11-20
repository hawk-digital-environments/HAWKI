<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\AssistantCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCannotListCategories(): void
    {
        $this->jsonApiRaw('get', '/api/hawki/v1/assistant-categories')
            ->assertStatus(401)
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function testCanListCategories(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $categories = AssistantCategory::factory()->count(3)->create();

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistant-categories')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($categories->sortBy('text')->values() as $i => $category) {
            $response->assertJson([
                'data' => [
                    $i => [
                        'id' => (string) $category->id,
                        'type' => 'assistant-categories',
                        'attributes' => [
                            'text' => $category->text,
                            'created_at' => $category->created_at->toJson(),
                            'updated_at' => $category->updated_at->toJson(),
                        ],
                    ],
                ],
            ]);
        }
    }

    public function testEmptyCategoriesReturnsEmptyData(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistant-categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testCategoriesAreOrderedAlphabetically(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        AssistantCategory::factory()->create(['text' => 'programming']);
        AssistantCategory::factory()->create(['text' => 'art']);
        AssistantCategory::factory()->create(['text' => 'education']);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistant-categories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'data' => [
                    ['attributes' => ['text' => 'art']],
                    ['attributes' => ['text' => 'education']],
                    ['attributes' => ['text' => 'programming']],
                ],
            ]);
    }

    public function testCategoriesPagination(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        AssistantCategory::factory()->count(20)->create();

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistant-categories?' . http_build_query(['page' => ['size' => 5]]))
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $response->assertJsonStructure([
            'meta' => [
                'page' => ['currentPage', 'from', 'to', 'perPage', 'lastPage', 'total'],
            ],
            'links' => ['first', 'last', 'next'],
        ]);
    }
}
