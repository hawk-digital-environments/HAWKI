<?php

namespace Tests\Feature\Api;

use App\Models\Assistants\Assistant;
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
        $this->getJson('/api/categories')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_can_list_categories(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $categories = Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($categories->sortBy('text')->values() as $i => $category) {
            $response->assertJson([
                'data' => [
                    $i => [
                        'id' => $category->id,
                        'text' => $category->text,
                        'created_at' => $category->created_at->toJson(),
                        'updated_at' => $category->updated_at->toJson(),
                    ],
                ],
            ]);
        }
    }

    public function test_empty_categories_returns_empty_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/categories')
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

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'data' => [
                    ['text' => 'art'],
                    ['text' => 'education'],
                    ['text' => 'programming'],
                ],
            ]);
    }
}
