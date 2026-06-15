<?php

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantCreated;
use App\Listeners\AssistantCreateInitialVersion;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\Category;
use App\Models\Assistants\Tag;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use AssistantFixture, RefreshDatabase;

    public function test_guest_cannot_create_assistant(): void
    {
        $this->jsonApi('post', '/api/assistants', $this->createJsonApiPayload())
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_create_assistant(): void
    {
        Event::fake(AssistantCreated::class);
        Event::assertListening(AssistantCreated::class, AssistantCreateInitialVersion::class);

        $category = Category::factory()->create(['text' => 'general']);

        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org']);
        $org->users()->attach($user);
        Sanctum::actingAs($user);

        $response = $this->jsonApi('post', '/api/assistants', $this->createJsonApiPayload([
            'name' => 'Test Assistant',
        ], [
            'category' => $category->id,
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('assistants', [
            'name' => 'Test Assistant',
            'creator_id' => $user->id,
            'remixed_creator_id' => null,
        ]);

        $this->assertNotNull(Assistant::first()->organization_id);

        $assistant = Assistant::first();
        $response
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'id' => (string) $assistant->id,
                    'type' => 'assistants',
                    'attributes' => [
                        'name' => 'Test Assistant',
                        'handle' => null,
                        'system_prompt' => 'You are a helpful assistant.',
                        'greeting' => 'Hello!',
                        'description' => 'A test assistant.',
                        'detail_description' => 'Detailed description here.',
                        'allow_remix' => true,
                        'allow_model_select' => false,
                        'release_stage' => 'private',
                        'model' => 'gpt-4',
                        'max_tokens' => 2048,
                        'temp' => 0.7,
                        'top_p' => 0.9,
                        'created_at' => $assistant->created_at->toJson(),
                        'updated_at' => $assistant->updated_at->toJson(),
                    ],
                ],
            ]);
    }

    public function test_can_create_assistant_with_user_prompts(): void
    {
        $category = Category::factory()->create(['text' => 'general']);
        $user = User::factory()->create();

        $temp = Assistant::factory()->create(['creator_id' => $user->id]);
        $prompt1 = $temp->user_prompts()->create(['text' => 'First prompt']);
        $prompt2 = $temp->user_prompts()->create(['text' => 'Second prompt']);

        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants?include=user_prompts', $this->createJsonApiPayload([], [
            'category' => $category->id,
            'user_prompts' => [$prompt1->id, $prompt2->id],
        ]))
            ->assertCreated();

        $assistant = Assistant::where('creator_id', $user->id)->where('id', '!=', $temp->id)->first();
        $this->assertNotNull($assistant);
        $this->assertEquals(2, $assistant->user_prompts()->count());
    }

    public function test_can_create_assistant_with_ai_tools(): void
    {
        $category = Category::factory()->create(['text' => 'general']);
        $tool = $this->createAiTool();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants?include=ai_tools', $this->createJsonApiPayload([], [
            'category' => $category->id,
            'ai_tools' => [$tool->id],
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_tools', [
            'ai_tool_id' => $tool->id,
        ]);
    }

    public function test_can_create_empty_assistant(): void
    {
        Event::fake(AssistantCreated::class);

        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org']);
        $org->users()->attach($user);
        Sanctum::actingAs($user);

        $response = $this->jsonApi('post', '/api/assistants', [
            'data' => ['type' => 'assistants'],
        ])
            ->assertCreated();

        $assistant = Assistant::first();
        $this->assertEquals($user->id, $assistant->creator_id);
        $this->assertNotNull($assistant->organization_id);
        $this->assertNull($assistant->category_id);
        $this->assertEquals(0, $assistant->tags()->count());
        $this->assertEquals(0, $assistant->ai_tools()->count());
        $this->assertEquals(0, $assistant->user_prompts()->count());

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'type' => 'assistants',
                'attributes' => [
                    'name' => '',
                    'handle' => null,
                    'system_prompt' => '',
                    'greeting' => '',
                    'description' => '',
                    'detail_description' => '',
                    'allow_remix' => false,
                    'allow_model_select' => false,
                    'release_stage' => 'draft',
                    'model' => '',
                    'max_tokens' => 0,
                    'temp' => 0,
                    'top_p' => 0,
                ],
            ],
        ]);
    }

    public function test_create_assistant_fails_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants', [
            'data' => [
                'type' => 'assistants',
                'attributes' => [
                    'name' => 12345,
                    'release_stage' => 'invalid-stage',
                    'temp' => 5.0,
                ],
            ],
        ])
            ->assertStatus(422);
    }

    public function test_create_fails_for_duplicate_handle(): void
    {
        $category = Category::factory()->create(['text' => 'general']);
        $user = User::factory()->create();
        Assistant::factory()->create(['handle' => 'unique-handle']);

        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants', $this->createJsonApiPayload(
            ['handle' => 'unique-handle'],
            ['category' => $category->id]
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/handle');
    }

    public function test_create_fails_for_nonexistent_ai_tool(): void
    {
        $category = Category::factory()->create(['text' => 'general']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants', $this->createJsonApiPayload([], [
            'category' => $category->id,
            'ai_tools' => [999999],
        ]))
            ->assertStatus(404);
    }

    public function test_can_create_assistant_with_tags(): void
    {
        $category = Category::factory()->create(['text' => 'general']);
        $tag1 = Tag::create(['text' => 'existing-tag']);
        $tag2 = Tag::create(['text' => 'new-tag']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants?include=tags', $this->createJsonApiPayload([], [
            'category' => $category->id,
            'tags' => [$tag1->id, $tag2->id],
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('tags', ['text' => 'existing-tag']);
        $this->assertDatabaseHas('tags', ['text' => 'new-tag']);

        $assistant = Assistant::first();
        $this->assertEquals(2, $assistant->tags()->count());
        $this->assertTrue($assistant->tags->pluck('text')->contains('existing-tag'));
        $this->assertTrue($assistant->tags->pluck('text')->contains('new-tag'));
    }

    public function test_create_fails_for_nonexistent_tag(): void
    {
        $category = Category::factory()->create(['text' => 'general']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants', $this->createJsonApiPayload([], [
            'category' => $category->id,
            'tags' => [999999],
        ]))
            ->assertStatus(404)
            ->assertJson([
                'errors' => [
                    [
                        'status' => '404',
                        'title' => 'Not Found',
                        'detail' => 'The related resource does not exist.',
                        'source' => ['pointer' => '/data/relationships/tags/data/0'],
                    ],
                ],
            ]);
    }
}
