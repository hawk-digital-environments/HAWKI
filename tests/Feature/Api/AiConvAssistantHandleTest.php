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
                        'hawkiExtensions' => ['assistant_handle' => 'math-tutor'],
                    ],
                ],
            ],
            $this->jsonApiHeaders(),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.attributes.hawkiExtensions.assistant_handle', 'math-tutor');

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
                        'hawkiExtensions' => ['assistant_handle' => 'does-not-exist'],
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
                        'hawkiExtensions' => ['assistant_handle' => 'math-tutor'],
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
                        'hawkiExtensions' => ['assistant_handle' => 'math-tutor'],
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
            ->assertJsonPath('data.attributes.hawkiExtensions.assistant_handle', 'math-tutor');
    }

    /**
     * Switching assistants mid-chat rebinds the conversation: the client
     * PATCHes the newly addressed handle after a send addressed to it.
     */
    public function testConversationUpdateSwitchesTheAssistantHandle(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'handle' => 'math-tutor',
        ]);
        Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'handle' => 'latin-tutor',
        ]);
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => 'tutoring-session',
            'user_id' => $user->id,
            'system_prompt' => null,
            'assistant_handle' => 'math-tutor',
        ]);

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}",
                [
                    'data' => [
                        'type' => 'ai-convs',
                        'id' => $conversation->slug,
                        'attributes' => [
                            'hawkiExtensions' => ['assistant_handle' => 'latin-tutor'],
                        ],
                    ],
                ],
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.attributes.hawkiExtensions.assistant_handle', 'latin-tutor');

        self::assertSame('latin-tutor', $conversation->fresh()->assistant_handle);
    }

    /**
     * Sending without an addressed assistant clears the binding: the
     * conversation continues as a plain HAWKI chat. Explicit `null` must
     * survive the JSON:API update (the client sends it on purpose).
     */
    public function testConversationUpdateClearsTheAssistantHandle(): void
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
            ->patchJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}",
                [
                    'data' => [
                        'type' => 'ai-convs',
                        'id' => $conversation->slug,
                        'attributes' => [
                            'hawkiExtensions' => ['assistant_handle' => null],
                        ],
                    ],
                ],
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.attributes.hawkiExtensions.assistant_handle', null);

        self::assertNull($conversation->fresh()->assistant_handle);
    }

    public function testConversationUpdateRejectsAnInvisibleAssistant(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
            'handle' => 'math-tutor',
        ]);

        $otherUser = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => 'tutoring-session',
            'user_id' => $otherUser->id,
            'system_prompt' => null,
            'assistant_handle' => null,
        ]);

        $this->actingAs($otherUser)
            ->patchJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}",
                [
                    'data' => [
                        'type' => 'ai-convs',
                        'id' => $conversation->slug,
                        'attributes' => [
                            'hawkiExtensions' => ['assistant_handle' => 'math-tutor'],
                        ],
                    ],
                ],
                $this->jsonApiHeaders(),
            )
            ->assertStatus(422);

        self::assertNull($conversation->fresh()->assistant_handle);
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

        $identity = ['name' => 'Math Tutor', 'icon' => '📚', 'tint' => 'hsl(200 60% 40%)', 'handle' => 'math-tutor'];

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
     * Thread replies ride the legacy `W.DDD` id scheme: the AI answer to a
     * thread reply gets the thread's next decimal id while keeping its
     * assistant identity, so the thread stays grouped and attributed after a
     * reload.
     */
    public function testThreadAiRepliesGetThreadMessageIdsAndRoundTripTheAssistantIdentity(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => 'tutoring-session',
            'user_id' => $user->id,
            'system_prompt' => null,
            'assistant_handle' => 'math-tutor',
        ]);

        // Trunk turn, then the user's thread reply.
        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(0),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.000');

        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(1),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.001');

        $identity = ['name' => 'Math Tutor', 'icon' => '📚', 'tint' => 'hsl(200 60% 40%)', 'handle' => 'math-tutor'];

        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(1, true, $identity),
            $this->jsonApiHeaders(),
        )
            ->assertCreated()
            ->assertJsonPath('data.attributes.message_id', '1.002')
            ->assertJsonPath('data.attributes.metadata.assistant', $identity);

        $this->actingAs($user)
            ->getJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}?include=messages",
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('included.2.attributes.message_id', '1.002')
            ->assertJsonPath('included.2.attributes.metadata.assistant', $identity);
    }

    /**
     * A regenerated thread answer is updated in place: switching the model and
     * the answering assistant replaces both on the stored message — the write
     * path the regenerate menu's picks rely on.
     */
    public function testUpdatingAThreadAiReplySwitchesTheModelAndReplacesTheAssistantIdentity(): void
    {
        $user = User::factory()->create();
        $conversation = AiConv::query()->create([
            'conv_name' => 'Tutoring session',
            'slug' => 'tutoring-session',
            'user_id' => $user->id,
            'system_prompt' => null,
            'assistant_handle' => 'math-tutor',
        ]);

        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(0),
            $this->jsonApiHeaders(),
        )->assertCreated();

        $identity = ['name' => 'Math Tutor', 'icon' => '📚', 'handle' => 'math-tutor'];
        $this->actingAs($user)->postJson(
            "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages",
            $this->messageBody(1, true, $identity),
            $this->jsonApiHeaders(),
        )->assertCreated()->assertJsonPath('data.attributes.message_id', '1.001');

        $replacedIdentity = ['name' => 'Latin Tutor', 'icon' => '🏛️', 'handle' => 'latin-tutor'];

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/ai-convs/{$conversation->slug}/actions/messages/1.001",
                [
                    'content' => [
                        'text' => [
                            'ciphertext' => 're-encrypted',
                            'iv' => 'iv-2',
                            'tag' => 'tag-2',
                        ],
                        'attachments' => [],
                    ],
                    'metadata' => [
                        'tools' => [],
                        'params' => null,
                        'assistant' => $replacedIdentity,
                    ],
                    'model' => 'switched-thread-model',
                    'completion' => true,
                ],
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.attributes.message_id', '1.001')
            ->assertJsonPath('data.attributes.model', 'switched-thread-model')
            ->assertJsonPath('data.attributes.metadata.assistant', $replacedIdentity);

        $stored = \App\Models\AiConvMsg::query()->where('message_id', '1.001')->sole();
        self::assertSame('switched-thread-model', $stored->model);
        self::assertSame($replacedIdentity, $stored->metadata['assistant']);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageBody(int $threadId, bool $isAi = false, ?array $assistantIdentity = null): array
    {
        return [
            'isAi' => $isAi,
            'threadId' => $threadId,
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
                'params' => null,
                ...($assistantIdentity !== null ? ['assistant' => $assistantIdentity] : []),
            ],
            'model' => $isAi ? 'assistant-model' : null,
            'completion' => true,
        ];
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
