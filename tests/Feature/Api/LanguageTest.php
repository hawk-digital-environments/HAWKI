<?php

namespace Tests\Feature\Api;

use App\Models\Assistants\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_languages(): void
    {
        $this->jsonApi('get', '/api/assistant-languages')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_languages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $languages = Language::factory()->count(3)->create();

        $response = $this->jsonApi('get', '/api/assistant-languages')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($languages->sortBy('text')->values() as $i => $language) {
            $response->assertJson([
                'data' => [
                    $i => [
                        'id' => (string) $language->id,
                        'type' => 'assistant-languages',
                        'attributes' => [
                            'text' => $language->text,
                            'created_at' => $language->created_at->toJson(),
                            'updated_at' => $language->updated_at->toJson(),
                        ],
                    ],
                ],
            ]);
        }
    }

    public function test_empty_languages_returns_empty_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistant-languages')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_languages_are_ordered_alphabetically(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Language::factory()->create(['text' => 'es']);
        Language::factory()->create(['text' => 'de']);
        Language::factory()->create(['text' => 'en']);

        $this->jsonApi('get', '/api/assistant-languages')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'data' => [
                    ['attributes' => ['text' => 'de']],
                    ['attributes' => ['text' => 'en']],
                    ['attributes' => ['text' => 'es']],
                ],
            ]);
    }

    public function test_languages_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Language::factory()->count(20)->create();

        $response = $this->jsonApi('get', '/api/assistant-languages?' . http_build_query(['page' => ['size' => 5]]))
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
