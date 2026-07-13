<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantCreated;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Listeners\AssistantCreateInitialVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

#[CoversNothing()]
class AssistantStoreTest extends TestCase
{
    use AssistantFixture;
    use RefreshDatabase;

    public function testGuestCannotCreateAssistant(): void
    {
        $this->jsonApiRaw('post', '/api/hawki/v1/assistants', $this->createJsonApiPayload())
            ->assertStatus(401)
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function testCanCreateAssistant(): void
    {
        Event::fake(AssistantCreated::class);
        Event::assertListening(AssistantCreated::class, AssistantCreateInitialVersion::class);

        $category = AssistantCategory::factory()->create(['text' => 'general']);

        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org']);
        $org->users()->attach($user);
        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistants', $this->createJsonApiPayload([
            'name' => 'Test Assistant',
        ], [
            'assistant_category' => $category->id,
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('assistants', [
            'name' => 'Test Assistant',
            'creator_id' => $user->id,
            'remixed_creator_id' => null,
        ]);

        self::assertNotNull(Assistant::first()->organization_id);

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
                        'release_stage' => 'draft',
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

    public function testCanCreateAssistantWithAiTools(): void
    {
        $category = AssistantCategory::factory()->create(['text' => 'general']);
        $tool = $this->createAiTool();

        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistants?include=ai_tools', $this->createJsonApiPayload([], [
            'assistant_category' => $category->id,
            'ai_tools' => [$tool->id],
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('assistant_tools', [
            'ai_tool_id' => $tool->id,
        ]);
    }

    public function testCanCreateEmptyAssistant(): void
    {
        Event::fake(AssistantCreated::class);

        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org']);
        $org->users()->attach($user);
        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistants', [
            'data' => ['type' => 'assistants'],
        ])
            ->assertCreated();

        $assistant = Assistant::first();
        self::assertEquals($user->id, $assistant->creator_id);
        self::assertNotNull($assistant->organization_id);
        self::assertNull($assistant->category_id);
        self::assertEquals(0, $assistant->assistantTags()->count());
        self::assertEquals(0, $assistant->ai_tools()->count());
        self::assertEquals(0, $assistant->assistantUserPrompts()->count());

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

    public function testCreateAssistantFailsValidation(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistants', [
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

    public function testCreateFailsForDuplicateHandle(): void
    {
        $category = AssistantCategory::factory()->create(['text' => 'general']);
        $user = User::factory()->create();
        Assistant::factory()->create(['handle' => '@unique-handle']);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistants', $this->createJsonApiPayload(
            ['handle' => '@unique-handle'],
            ['assistant_category' => $category->id],
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/handle');
    }

    public function testCreateFailsForInvalidHandleFormat(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistants', $this->createJsonApiPayload(
            ['handle' => 'in valid'],
        ))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/handle');
    }

    public function testCreateFailsForNonexistentAiTool(): void
    {
        $category = AssistantCategory::factory()->create(['text' => 'general']);
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistants', $this->createJsonApiPayload([], [
            'assistant_category' => $category->id,
            'ai_tools' => [999999],
        ]))
            ->assertStatus(404);
    }

    public function testCanCreateAssistantWithoutTags(): void
    {
        $category = AssistantCategory::factory()->create(['text' => 'general']);

        $user = User::factory()->create();
        $this->actingAsUser($user);

        // Tags are attached via the assistant_tags relationship, not via the create payload.
        $this->jsonApiRaw('post', '/api/hawki/v1/assistants', $this->createJsonApiPayload([], [
            'assistant_category' => $category->id,
        ]))
            ->assertCreated();

        $assistant = Assistant::first();
        self::assertEquals(0, $assistant->assistantTags()->count());
    }
}
