<?php

namespace Tests\Feature\Api;

use App\Models\Assistants\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_guest_cannot_list_tags(): void
    {
        $this->jsonApi('get', '/api/tags')
            ->assertUnauthorized();
    }

    public function test_can_list_tags(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Tag::create(['text' => 'php']);
        Tag::create(['text' => 'laravel']);

        $this->jsonApi('get', '/api/tags')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tag = Tag::create(['text' => 'php']);

        $this->jsonApi('get', "/api/tags/{$tag->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $tag->id,
                    'type' => 'tags',
                    'attributes' => [
                        'text' => 'php',
                    ],
                ],
            ]);
    }

    public function test_can_create_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/tags', [
            'data' => [
                'type' => 'tags',
                'attributes' => [
                    'text' => 'new-tag',
                ],
            ],
        ])
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'type' => 'tags',
                    'attributes' => [
                        'text' => 'new-tag',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('tags', ['text' => 'new-tag']);
    }

    public function test_cannot_create_duplicate_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Tag::create(['text' => 'existing-tag']);

        $this->jsonApi('post', '/api/tags', [
            'data' => [
                'type' => 'tags',
                'attributes' => [
                    'text' => 'existing-tag',
                ],
            ],
        ])
            ->assertUnprocessable();
    }

    public function test_cannot_create_tag_without_text(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/tags', [
            'data' => [
                'type' => 'tags',
                'attributes' => [
                    'text' => '',
                ],
            ],
        ])
            ->assertUnprocessable();
    }

    public function test_can_delete_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tag = Tag::create(['text' => 'to-delete']);

        $this->jsonApi('delete', "/api/tags/{$tag->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}
