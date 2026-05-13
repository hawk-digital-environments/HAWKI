<?php

namespace Tests\Feature\Api;

use App\Models\Assistants\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_categories(): void
    {
        $this->jsonApi('get', '/api/categories')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_categories(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $categories = Category::factory()->count(3)->create();

        $response = $this->jsonApi('get', '/api/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($categories->sortBy('text')->values() as $i => $category) {
            $response->assertJson([
                'data' => [
                    $i => [
                        'id' => (string) $category->id,
                        'type' => 'categories',
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

    public function test_empty_categories_returns_empty_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_categories_are_ordered_alphabetically(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Category::factory()->create(['text' => 'programming']);
        Category::factory()->create(['text' => 'art']);
        Category::factory()->create(['text' => 'education']);

        $this->jsonApi('get', '/api/categories')
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

    public function test_categories_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Category::factory()->count(20)->create();

        $response = $this->jsonApi('get', '/api/categories?' . http_build_query(['page' => ['size' => 5]]))
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
