<?php

namespace Tests\Feature\Api;

use App\Models\Ai\Tools\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Assistant',
            'system_prompt' => 'You are a helpful assistant.',
            'greeting' => 'Hello!',
            'description' => 'A test assistant.',
            'detail_description' => 'Detailed description here.',
            'allow_remix' => true,
            'allow_model_select' => false,
            'language' => 'en',
            'category' => 'general',
            'review_stage' => 'draft',
            'formality' => 'neutral',
            'model' => 'gpt-4',
            'model_length' => 2048,
            'model_temp' => 0.7,
            'model_top_p' => 0.9,
        ], $overrides);
    }

    private function createAiTool(): AiTool
    {
        $serverId = DB::table('mcp_servers')->insertGetId([
            'url' => 'https://example.com/mcp/' . uniqid(),
            'server_label' => 'Test Server ' . uniqid(),
            'timeout' => '10',
            'discovery_timeout' => '10',
            'api_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AiTool::create([
            'type' => 'function',
            'name' => 'test_tool_' . uniqid(),
            'description' => 'A test tool',
            'status' => 'active',
            'server_id' => $serverId,
        ]);
    }

    public function test_guest_cannot_list_assistants(): void
    {
        $this->getJson('/api/assistants')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_guest_cannot_create_assistant(): void
    {
        $this->postJson('/api/assistants', $this->createPayload())
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_can_list_assistants(): void
    {
        $user = User::factory()->create();
        $assistants = Assistant::factory()->count(3)->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assistants')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($assistants as $i => $assistant) {
            $response
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    $i => [
                        'id' => $assistant->id,
                        'name' => $assistant->name,
                        'handle' => $assistant->handle,
                        'system_prompt' => $assistant->system_prompt,
                        'greeting' => $assistant->greeting,
                        'description' => $assistant->description,
                        'detail_description' => $assistant->detail_description,
                        'allow_remix' => (int) $assistant->allow_remix,
                        'allow_model_select' => (int) $assistant->allow_model_select,
                        'language' => $assistant->language,
                        'category' => $assistant->category,
                        'review_stage' => $assistant->review_stage,
                        'formality' => $assistant->formality,
                        'model' => $assistant->model,
                        'model_length' => $assistant->model_length,
                        'model_temp' => $assistant->model_temp,
                        'model_top_p' => $assistant->model_top_p,
                        'creator_id' => $assistant->creator_id,
                        'original_creator_id' => $assistant->original_creator_id,
                        'original_assistant_id' => null,
                        'created_at' => $assistant->created_at->toJson(),
                        'updated_at' => $assistant->updated_at->toJson(),
                    ],
                ],
            ]);
        }
    }

    public function test_can_list_assistants_with_relations(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assistants?with=creator,user_prompts')
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertEquals($assistant->id, $item['id']);
        $this->assertArrayHasKey('creator', $item);
        $this->assertArrayHasKey('user_prompts', $item);
    }

    public function test_list_ignores_disallowed_relations(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assistants?with=attachments,creator')
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertEquals($assistant->id, $item['id']);
        $this->assertArrayHasKey('creator', $item);
        $this->assertArrayNotHasKey('attachments', $item);
    }

    public function test_can_create_assistant(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/assistants', $this->createPayload())
            ->assertCreated();

        $this->assertDatabaseHas('assistants', [
            'name' => 'Test Assistant',
            'creator_id' => $user->id,
            'original_creator_id' => $user->id,
        ]);

        $assistant = Assistant::first();
        $response
         ->assertStatus(201)    
        ->assertJson([
            'data' => [
                'id' => $assistant->id,
                'name' => 'Test Assistant',
                'handle' => null,
                'system_prompt' => 'You are a helpful assistant.',
                'greeting' => 'Hello!',
                'description' => 'A test assistant.',
                'detail_description' => 'Detailed description here.',
                'allow_remix' => true,
                'allow_model_select' => false,
                'language' => 'en',
                'category' => 'general',
                'review_stage' => 'draft',
                'formality' => 'neutral',
                'model' => 'gpt-4',
                'model_length' => 2048,
                'model_temp' => 0.7,
                'model_top_p' => 0.9,
                'creator_id' => $user->id,
                'original_creator_id' => $user->id,
                'original_assistant_id' => null,
                'created_at' => $assistant->created_at->toJson(),
                'updated_at' => $assistant->updated_at->toJson(),
                'user_prompts' => [],
                'ai_tools' => [],
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
                'id' => $assistant->id,
                'creator_id' => $user->id,
                'original_creator_id' => $user->id,
                'created_at' => $assistant->created_at->toJson(),
                'updated_at' => $assistant->updated_at->toJson(),
                'user_prompts' => [
                    ['text' => 'First prompt'],
                    ['text' => 'Second prompt'],
                ],
            ],
        ]);
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

        $assistant = Assistant::first();
        $response->assertJson([
            'data' => [
                'id' => $assistant->id,
                'creator_id' => $user->id,
                'original_creator_id' => $user->id,
                'created_at' => $assistant->created_at->toJson(),
                'updated_at' => $assistant->updated_at->toJson(),
                'ai_tools' => [
                    ['id' => $tool->id],
                ],
            ],
        ]);
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
                'review_stage',
                'formality',
                'model',
                'model_length',
                'model_temp',
                'model_top_p',
            ]);
    }

    public function test_can_show_assistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $assistant->id,
                    'name' => $assistant->name,
                    'handle' => $assistant->handle,
                    'system_prompt' => $assistant->system_prompt,
                    'greeting' => $assistant->greeting,
                    'description' => $assistant->description,
                    'detail_description' => $assistant->detail_description,
                    'allow_remix' => (int) $assistant->allow_remix,
                    'allow_model_select' => (int) $assistant->allow_model_select,
                    'language' => $assistant->language,
                    'category' => $assistant->category,
                    'review_stage' => $assistant->review_stage,
                    'formality' => $assistant->formality,
                    'model' => $assistant->model,
                    'model_length' => $assistant->model_length,
                    'model_temp' => $assistant->model_temp,
                    'model_top_p' => $assistant->model_top_p,
                    'creator_id' => $assistant->creator_id,
                    'original_creator_id' => $assistant->original_creator_id,
                    'original_assistant_id' => null,
                    'created_at' => $assistant->created_at->toJson(),
                    'updated_at' => $assistant->updated_at->toJson(),
                ],
            ]);
    }

    public function test_can_show_assistant_with_relations(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/assistants/{$assistant->id}?with=creator,user_prompts,ai_tools,tags")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $assistant->id,
                    'created_at' => $assistant->created_at->toJson(),
                    'updated_at' => $assistant->updated_at->toJson(),
                ],
            ]);

        $json = $response->json('data');
        $this->assertArrayHasKey('creator', $json);
        $this->assertArrayHasKey('user_prompts', $json);
        $this->assertArrayHasKey('ai_tools', $json);
        $this->assertArrayHasKey('tags', $json);
    }

    public function test_can_update_assistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/assistants/{$assistant->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description.',
        ])
            ->assertOk();

        $assistant->refresh();
        $response->assertJson([
            'data' => [
                'id' => $assistant->id,
                'name' => 'Updated Name',
                'description' => 'Updated description.',
                'handle' => $assistant->handle,
                'system_prompt' => $assistant->system_prompt,
                'greeting' => $assistant->greeting,
                'detail_description' => $assistant->detail_description,
                'allow_remix' => (int) $assistant->allow_remix,
                'allow_model_select' => (int) $assistant->allow_model_select,
                'language' => $assistant->language,
                'category' => $assistant->category,
                'review_stage' => $assistant->review_stage,
                'formality' => $assistant->formality,
                'model' => $assistant->model,
                'model_length' => $assistant->model_length,
                'model_temp' => $assistant->model_temp,
                'model_top_p' => $assistant->model_top_p,
                'creator_id' => $user->id,
                'original_creator_id' => $assistant->original_creator_id,
                'original_assistant_id' => null,
                'created_at' => $assistant->created_at->toJson(),
                'updated_at' => $assistant->updated_at->toJson(),
            ],
        ]);

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_cannot_update_others_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->putJson("/api/assistants/{$assistant->id}", [
            'name' => 'Hacked',
        ])
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_can_delete_assistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/assistants/{$assistant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('assistants', ['id' => $assistant->id]);
    }

    public function test_cannot_delete_other_user_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->deleteJson("/api/assistants/{$assistant->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('assistants', ['id' => $assistant->id]);
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

    public function test_update_assistant_increments_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/assistants/{$assistant->id}", [
            'name' => 'Updated Name',
        ])
            ->assertOk();

        $this->assertDatabaseHas('versions', [
            'assistant_id' => $assistant->id,
            'text' => 'Updated',
            'version' => 2.0,
        ]);
    }

    public function test_update_assistant_with_version_text(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/assistants/{$assistant->id}", [
            'name' => 'Updated Name',
            'version_text' => 'Changed the name',
        ])
            ->assertOk();

        $this->assertDatabaseHas('versions', [
            'assistant_id' => $assistant->id,
            'text' => 'Changed the name',
            'version' => 2.0,
        ]);
    }

    public function test_multiple_updates_increment_version(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/assistants/{$assistant->id}", ['name' => 'v2'])
            ->assertOk();

        $this->putJson("/api/assistants/{$assistant->id}", ['name' => 'v3'])
            ->assertOk();

        $versions = $assistant->fresh()->versions()->orderBy('version')->get();

        $this->assertCount(3, $versions);
        $this->assertEquals(1.0, (float) $versions[0]->version);
        $this->assertEquals(2.0, (float) $versions[1]->version);
        $this->assertEquals(3.0, (float) $versions[2]->version);
    }

    public function test_can_list_assistants_with_versions(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assistants?with=versions')
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertArrayHasKey('versions', $item);
        $this->assertCount(1, $item['versions']);
        $this->assertEquals('Initial version', $item['versions'][0]['text']);
        $this->assertEquals(1.0, (float) $item['versions'][0]['version']);
    }

    public function test_can_show_assistant_with_versions(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/assistants/{$assistant->id}?with=versions")
            ->assertOk();

        $json = $response->json('data');
        $this->assertArrayHasKey('versions', $json);
        $this->assertCount(1, $json['versions']);
        $this->assertEquals('Initial version', $json['versions'][0]['text']);
    }
}
