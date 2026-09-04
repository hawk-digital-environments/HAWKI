<?php
declare(strict_types=1);

namespace Tests\Feature\Services\Ai\Agents;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Implementations\Chat\AssistantChatAgentFactory;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Exercises the assistant agent factory through the real container: the
 * AgentRegistry wiring (assistant factory before the plain chat factory),
 * the real run composer and the acting user resolution.
 */
#[CoversClass(AssistantChatAgentFactory::class)]
class AssistantAgentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function testItResolvesAnExplicitAssistantHandleThroughTheAgentRegistry(): void
    {
        $user = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'handle' => 'math-tutor',
            'model' => 'assistant-model',
            'allow_model_select' => false,
            'system_prompt' => 'You are the math tutor.',
            'max_tokens' => 0,
        ]);
        $this->actingAs($user);

        $captured = new \ArrayObject();
        $agent = $this->createStub(AgentInterface::class);
        $this->mockDelegatingChatFactory($captured, $agent);

        $resolved = $this->app->get(AiService::class)->getAgent([
            'payload' => [
                'model' => 'user-model',
                'assistant_handle' => 'math-tutor',
                'messages' => [
                    ['role' => 'system', 'content' => ['text' => 'Conversation prompt.']],
                    ['role' => 'user', 'content' => ['text' => '@math-tutor explain derivatives']],
                ],
            ],
        ]);

        static::assertSame($agent, $resolved);

        $payload = $captured[0]['payload'];
        static::assertSame('assistant-model', $payload['model']);
        static::assertStringContainsString('You are the math tutor.', $payload['messages'][0]['content']['text']);
        static::assertStringNotContainsString('Conversation prompt.', $payload['messages'][0]['content']['text']);
        static::assertSame('explain derivatives', $payload['messages'][1]['content']['text']);
    }

    public function testItFallsBackToThePlainChatFactoryForInvisibleAssistants(): void
    {
        $owner = User::factory()->create();
        Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
            'handle' => 'math-tutor',
            'model' => 'assistant-model',
        ]);
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $captured = new \ArrayObject();
        $agent = $this->createStub(AgentInterface::class);
        $this->mockDelegatingChatFactory($captured, $agent);

        $resolved = $this->app->get(AiService::class)->getAgent([
            'payload' => [
                'model' => 'user-model',
                'assistant_handle' => 'math-tutor',
                'messages' => [
                    ['role' => 'system', 'content' => ['text' => 'Conversation prompt.']],
                    ['role' => 'user', 'content' => ['text' => '@math-tutor explain derivatives']],
                ],
            ],
        ]);

        static::assertSame($agent, $resolved);

        // The plain chat factory received the payload unchanged: the
        // assistant factory declined, so no prompt/model rewrite happened.
        $payload = $captured[0]['payload'];
        static::assertSame('user-model', $payload['model']);
        static::assertSame('Conversation prompt.', $payload['messages'][0]['content']['text']);
        static::assertSame('@math-tutor explain derivatives', $payload['messages'][1]['content']['text']);
    }

    /**
     * Replaces the delegated plain chat factory with a Mockery spy that
     * records every request it receives and returns the given agent.
     */
    private function mockDelegatingChatFactory(\ArrayObject $captured, AgentInterface $agent): void
    {
        $this->mock(ChatAgentFromLegacyRequestFactory::class, static function ($mock) use ($captured, $agent): void {
            $mock->shouldReceive('createAgent')
                ->once()
                ->with(\Mockery::on(static function (mixed $request) use ($captured): bool {
                    $captured[] = $request;

                    return true;
                }))
                ->andReturn($agent);
        });
    }
}
