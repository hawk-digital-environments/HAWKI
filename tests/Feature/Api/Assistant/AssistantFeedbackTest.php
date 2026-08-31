<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function testViewerCanAddFeedbackToVisibleAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        $this->actingAsUser($viewer);

        $response = $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Great assistant!'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'assistant-feedback');
        $response->assertJsonPath('data.attributes.text', 'Great assistant!');
        self::assertNotNull($response->json('data.id'));

        $this->assertDatabaseHas('assistant_feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
            'text' => 'Great assistant!',
        ]);
    }

    public function testCreatorCanAddFeedbackToOwnAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'My own feedback'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('assistant_feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'My own feedback',
        ]);
    }

    public function testCannotAddFeedbackToPrivateAssistantOfOtherUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        $this->actingAsUser($otherUser);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Should fail'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();

        self::assertEquals(0, AssistantFeedback::count());
    }

    public function testFeedbackRequiresText(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => (object) [],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(422);

        self::assertEquals(0, AssistantFeedback::count());
    }

    public function testGuestCannotAddFeedback(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Guest feedback'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(401);
    }

    public function testClientCannotSetAuthor(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->actingAsUser($attacker);

        // Attempt to spoof the author relationship; server must ignore it.
        $this->jsonApiRaw('post', '/api/hawki/v1/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Spoofed'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'user' => ['data' => ['type' => 'users', 'id' => (string) $owner->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('assistant_feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $attacker->id,
        ]);
    }
}
