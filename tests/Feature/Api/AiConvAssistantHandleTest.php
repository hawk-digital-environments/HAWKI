<?php
declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AiConv;
use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AiConvAssistantHandleTest extends TestCase
{
    use RefreshDatabase;

    public function testConversationCreationPersistsTheAssistantHandle(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'handle' => 'math-tutor',
        ]);

        $response = $this->actingAs($user)->postJson(
            '/api/hawki/v1/ai-convs',
            [
                'data' => [
                    'type' => 'ai-convs',
                    'attributes' => [
                        'name' => 'Tutoring session',
                        'system_prompt' => null,
                        'assistant_handle' => 'math-tutor',
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.attributes.assistant_handle', 'math-tutor');

        $conversation = AiConv::query()->sole();
        self::assertSame('math-tutor', $conversation->assistant_handle);
    }

    public function testConversationCreationRejectsAnUnknownAssistantHandle(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(
            '/api/hawki/v1/ai-convs',
            [
                'data' => [
                    'type' => 'ai-convs',
                    'attributes' => [
                        'name' => 'Broken session',
                        'system_prompt' => null,
                        'assistant_handle' => 'does-not-exist',
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        )->assertStatus(422);
    }

    public function testConversationCreationRejectsAnInvisibleAssistant(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
            'handle' => 'math-tutor',
        ]);

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->postJson(
            '/api/hawki/v1/ai-convs',
            [
                'data' => [
                    'type' => 'ai-convs',
                    'attributes' => [
                        'name' => 'Forbidden session',
                        'system_prompt' => null,
                        'assistant_handle' => 'math-tutor',
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        )->assertStatus(422);
    }

    public function testConversationCreationAllowsPubliclyVisibleAssistantsOfOtherUsers(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'federated',
            'handle' => 'math-tutor',
        ]);

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->postJson(
            '/api/hawki/v1/ai-convs',
            [
                'data' => [
                    'type' => 'ai-convs',
                    'attributes' => [
                        'name' => 'Public session',
                        'system_prompt' => null,
                        'assistant_handle' => 'math-tutor',
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        )->assertCreated();
    }

    public function testConversationShowReturnsTheAssistantHandle(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => 'tutoring-session',
            'user_id' => $user->id,
            'system_prompt' => null,
            'assistant_handle' => 'math-tutor',
        ]);

        $this->actingAs($user)
            ->getJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}",
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.attributes.assistant_handle', 'math-tutor');
    }

    /**
     * The assistant display identity travels inside the AI message
     * metadata; it must survive the store round-trip so the message log
     * keeps showing who answered after the stream is persisted (and on
     * reload — see the conversation include below).
     */
    public function testStoredAiMessageRoundTripsTheAssistantIdentity(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => 'tutoring-session',
            'user_id' => $user->id,
            'system_prompt' => null,
            'assistant_handle' => 'math-tutor',
        ]);

        $identity = ['name' => 'Math Tutor', 'icon' => '📚', 'tint' => 'hsl(200 60% 40%)'];

        $this->actingAs($user)
            ->postJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
                [
                    'isAi' => true,
                    'threadId' => 0,
                    'content' => [
                        'text' => [
                            'ciphertext' => 'encrypted',
                            'iv' => 'iv',
                            'tag' => 'tag',
                        ],
                        'attachments' => [],
                    ],
                    'metadata' => [
                        'tools' => [],
                        'params' => ['temp' => 0.3],
                        'assistant' => $identity,
                    ],
                    'model' => 'assistant-model',
                    'completion' => true,
                ],
                $this->jsonApiHeaders(),
            )
            ->assertCreated()
            ->assertJsonPath('data.attributes.metadata.assistant', $identity);

        $this->actingAs($user)
            ->getJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}?include=messages",
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('included.0.attributes.metadata.assistant', $identity);
    }

    /**
     * @return array<string, string>
     */
    private function jsonApiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json,application/json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }
}
