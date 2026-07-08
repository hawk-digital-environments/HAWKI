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
        $this->jsonApi('get', '/api/assistant-tags')
            ->assertUnauthorized();
    }

    public function test_can_list_tags(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Tag::create(['text' => 'php']);
        Tag::create(['text' => 'laravel']);

        $this->jsonApi('get', '/api/assistant-tags')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tag = Tag::create(['text' => 'php']);

        $this->jsonApi('get', "/api/assistant-tags/{$tag->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $tag->id,
                    'type' => 'assistant-tags',
                    'attributes' => [
                        'text' => 'php',
                    ],
                ],
            ]);
    }

    public function test_any_authenticated_user_can_create_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistant-tags', [
            'data' => [
                'type' => 'assistant-tags',
                'attributes' => [
                    'text' => 'new-tag',
                ],
            ],
        ])
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'type' => 'assistant-tags',
                    'attributes' => [
                        'text' => 'new-tag',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('tags', ['text' => 'new-tag']);
    }

    public function test_cannot_create_tag_without_text(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistant-tags', [
            'data' => [
                'type' => 'assistant-tags',
                'attributes' => [
                    'text' => '',
                ],
            ],
        ])
            ->assertUnprocessable();
    }

    public function test_cannot_create_duplicate_tag_text(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Tag::create(['text' => 'php']);

        $this->jsonApi('post', '/api/assistant-tags', [
            'data' => [
                'type' => 'assistant-tags',
                'attributes' => [
                    'text' => 'php',
                ],
            ],
        ])
            ->assertUnprocessable();
    }
}
