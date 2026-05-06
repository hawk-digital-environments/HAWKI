<?php

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantCreated;
use App\Listeners\AssistantCreateInitialVersion;
use App\Models\Assistants\Assistant;
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
    use RefreshDatabase, AssistantFixture;

    public function test_guest_cannot_create_assistant(): void
    {
        $this->postJson('/api/assistants', $this->createPayload())
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_can_create_assistant(): void
    {
        Event::fake(AssistantCreated::class);
        Event::assertListening(AssistantCreated::class, AssistantCreateInitialVersion::class);

        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org']);
        $org->users()->attach($user);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assistants', $this->createPayload())
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
                        'formality' => 'neutral',
                        'model' => 'gpt-4',
                        'model_length' => 2048,
                        'model_temp' => 0.7,
                        'model_top_p' => 0.9,
                        'created_at' => $assistant->created_at->toJson(),
                        'updated_at' => $assistant->updated_at->toJson(),
                    ],
                ],
            ]);
    }

    public function test_can_create_assistant_with_user_prompts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assistants', $this->createPayload([
            'user_prompts' => [
                ['text' => 'First prompt'],
                ['text' => 'Second prompt'],
            ],
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('user_prompts', ['text' => 'First prompt']);
        $this->assertDatabaseHas('user_prompts', ['text' => 'Second prompt']);

        $assistant = Assistant::first();
        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
                'type' => 'assistants',
            ],
        ]);

        $included = collect($response->json('included'));
        $promptResources = $included->filter(fn ($item) => $item['type'] === 'user_prompts');
        $this->assertCount(2, $promptResources);
        $texts = $promptResources->pluck('attributes.text')->toArray();
        $this->assertContains('First prompt', $texts);
        $this->assertContains('Second prompt', $texts);
    }

    public function test_can_create_assistant_with_ai_tools(): void
    {
        $tool = $this->createAiTool();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assistants', $this->createPayload([
            'ai_tools' => [['id' => $tool->id]],
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_tools', [
            'ai_tool_id' => $tool->id,
        ]);

        $included = collect($response->json('included'));
        $toolResource = $included->first(fn ($item) => $item['type'] === 'ai_tools');
        $this->assertNotNull($toolResource);
        $this->assertEquals((string) $tool->id, $toolResource['id']);
    }

    public function test_create_assistant_fails_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/assistants', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'greeting',
                'description',
                'language',
                'category',
                'release_stage',
                'formality',
                'model',
                'model_length',
                'model_temp',
                'model_top_p',
            ]);
    }

    public function test_create_fails_for_duplicate_handle(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['handle' => 'unique-handle']);

        Sanctum::actingAs($user);

        $this->postJson('/api/assistants', $this->createPayload(['handle' => 'unique-handle']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['handle']);
    }

    public function test_create_fails_for_nonexistent_ai_tool(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/assistants', $this->createPayload([
            'ai_tools' => [['id' => 999999]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ai_tools.0.id']);
    }

    public function test_create_assistant_creates_version_1(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/assistants', $this->createPayload())
            ->assertCreated();

        $this->assertDatabaseHas('versions', [
            'assistant_id' => Assistant::first()->id,
            'text' => 'Initial version',
            'version' => 1.0,
        ]);

        $this->assertEquals(1, Assistant::first()->versions()->count());
    }

    public function test_can_create_assistant_with_tags(): void
    {
        Tag::create(['text' => 'existing-tag']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assistants', $this->createPayload([
            'tags' => ['existing-tag', 'new-tag'],
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('tags', ['text' => 'existing-tag']);
        $this->assertDatabaseHas('tags', ['text' => 'new-tag']);

        $assistant = Assistant::first();
        $this->assertEquals(2, $assistant->tags()->count());
        $this->assertTrue($assistant->tags->pluck('text')->contains('existing-tag'));
        $this->assertTrue($assistant->tags->pluck('text')->contains('new-tag'));

        $response->assertJson([
            'data' => [
                'id' => (string) $assistant->id,
            ],
        ]);

        $included = collect($response->json('included'));
        $tagResources = $included->filter(fn ($item) => $item['type'] === 'tags');
        $tagTexts = $tagResources->pluck('attributes.text')->toArray();
        $this->assertContains('existing-tag', $tagTexts);
        $this->assertContains('new-tag', $tagTexts);
    }

    public function test_create_fails_for_nonexistent_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/assistants', $this->createPayload([
            'tags' => [12345],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tags.0']);
    }
}
