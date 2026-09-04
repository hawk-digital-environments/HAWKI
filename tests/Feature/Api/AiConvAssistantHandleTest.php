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
