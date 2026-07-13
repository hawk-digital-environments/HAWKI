<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\AssistantTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantTagTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCannotListTags(): void
    {
        $this->jsonApiRaw('get', '/api/hawki/v1/assistant-tags')
            ->assertStatus(401);
    }

    public function testCanListTags(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        AssistantTag::create(['text' => 'php']);
        AssistantTag::create(['text' => 'laravel']);

        $this->jsonApiRaw('get', '/api/hawki/v1/assistant-tags')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testCanShowTag(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $tag = AssistantTag::create(['text' => 'php']);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistant-tags/{$tag->id}")
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

    public function testAnyAuthenticatedUserCanCreateTag(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-tags', [
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

        $this->assertDatabaseHas('assistant_tags', ['text' => 'new-tag']);
    }

    public function testCannotCreateTagWithoutText(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-tags', [
            'data' => [
                'type' => 'assistant-tags',
                'attributes' => [
                    'text' => '',
                ],
            ],
        ])
            ->assertUnprocessable();
    }

    public function testCannotCreateDuplicateTagText(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        AssistantTag::create(['text' => 'php']);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-tags', [
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
