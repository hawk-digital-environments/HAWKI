<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Chat;

use App\Http\Controllers\Api\V1\ChatController;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Chat\Factories\ChatAgentRegistry;
use App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface;
use App\Services\Ai\Chat\Values\AiRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall as VendorToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\ParsesSseStreams;
use Tests\Feature\Api\Chat\ChatEndpointTestFixtures\FakeChatAgent;
use Tests\TestCase;

/**
 * Covers POST /api/hawki/v1/chat/openai — the Chat Completions wire format — driving
 * the full formatter → service → normalizer stack against a deterministic fake agent.
 */
#[CoversClass(ChatController::class)]
class ChatCompletionsEndpointTest extends TestCase
{
    use ParsesSseStreams;
    use RefreshDatabase;
    private const string ENDPOINT = '/api/hawki/v1/chat/openai';

    public function testGuestCannotChat(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertUnauthorized();
    }

    public function testItReturnsSseContentType(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([new TextDelta('e1', 'm1', 'Hi', 1)]);

        $response = $this->postJson(self::ENDPOINT, $this->payload(stream: true));

        $response->assertStatus(200);
        self::assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function testItEmitsTheFullChunkGrammarWithoutEventLines(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new TextDelta('e2', 'm1', 'Hel', 1002),
            new TextDelta('e3', 'm1', 'lo', 1003),
            new StreamEnd('e5', 'stop', new Usage(promptTokens: 5, completionTokens: 7), 1005),
        ]);

        [$response, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $response->assertStatus(200);

        self::assertStringNotContainsString('event:', $body);
        self::assertStringEndsWith("data: [DONE]\n\n", $body);

        $frames = $this->parseDataFrames($body);
        $chunks = array_map(static fn (array $frame): mixed => $frame['data'], $frames);
        $last = array_key_last($chunks);

        self::assertSame('[DONE]', $chunks[$last]);

        $roleChunk = $chunks[0];
        self::assertSame('chat.completion.chunk', $roleChunk['object']);
        self::assertSame('chatcmpl-s1', $roleChunk['id']);
        self::assertSame(['role' => 'assistant', 'content' => 'Hel'], $roleChunk['choices'][0]['delta']);

        self::assertSame(['content' => 'lo'], $chunks[1]['choices'][0]['delta']);

        $finishChunk = $chunks[$last - 2];
        self::assertSame([], $finishChunk['choices'][0]['delta']);
        self::assertSame('stop', $finishChunk['choices'][0]['finish_reason']);

        $usageChunk = $chunks[$last - 1];
        self::assertSame([], $usageChunk['choices']);
        self::assertSame(['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12], $usageChunk['usage']);
    }

    public function testItStreamsToolCallsWithIndexAddressing(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new ToolCall('e1', new VendorToolCall(id: 'call_1', name: 'read_file', arguments: ['path' => '/x']), 1001),
            new StreamEnd('e2', 'tool_calls', new Usage(), 1002),
        ]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $chunks = array_map(static fn (array $frame): mixed => $frame['data'], $this->parseDataFrames($body));

        $toolChunks = array_values(array_filter(
            $chunks,
            static fn (mixed $chunk): bool => \is_array($chunk) && isset($chunk['choices'][0]['delta']['tool_calls']),
        ));

        $start = $toolChunks[0]['choices'][0]['delta']['tool_calls'][0];
        self::assertSame(0, $start['index']);
        self::assertSame('call_1', $start['id']);
        self::assertSame('function', $start['type']);
        self::assertSame('read_file', $start['function']['name']);

        $delta = $toolChunks[1]['choices'][0]['delta']['tool_calls'][0];
        self::assertSame(0, $delta['index']);
        self::assertArrayNotHasKey('id', $delta);
        self::assertSame('{"path":"/x"}', $delta['function']['arguments']);

        $finishChunk = $chunks[array_key_last($chunks) - 2];
        self::assertSame('tool_calls', $finishChunk['choices'][0]['finish_reason']);
    }

    public function testItServesNonStreamingToolCallResponses(): void
    {
        $this->actingAsUser(User::factory()->create());
        $response = new AgentResponse(
            invocationId: 'inv_1',
            text: '',
            usage: new Usage(promptTokens: 3, completionTokens: 4),
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        );
        $response->toolCalls = collect([new VendorToolCall(id: 'call_1', name: 'read_file', arguments: ['path' => '/x'])]);
        $response->steps = collect([new \Laravel\Ai\Responses\Data\Step(
            text: '',
            toolCalls: [],
            toolResults: [],
            finishReason: \Laravel\Ai\Responses\Data\FinishReason::ToolCalls,
            usage: new Usage(),
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        )]);
        $this->mockAgent([], $response);

        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response->assertOk();
        $body = $response->json();
        self::assertSame('chat.completion', $body['object']);
        self::assertSame('chatcmpl-inv_1', $body['id']);
        self::assertSame('tool_calls', $body['choices'][0]['finish_reason']);
        self::assertNull($body['choices'][0]['message']['content']);
        self::assertSame('call_1', $body['choices'][0]['message']['tool_calls'][0]['id']);
        self::assertSame('{"path":"/x"}', $body['choices'][0]['message']['tool_calls'][0]['function']['arguments']);
        self::assertSame(['prompt_tokens' => 3, 'completion_tokens' => 4, 'total_tokens' => 7], $body['usage']);
    }

    public function testItCompletesTheClientToolLoopAcrossTurns(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([new StreamEnd('e1', 'stop', new Usage(), 1000)]);

        // Turn 2 of a client-driven tool loop: assistant tool_calls + tool result follow-up.
        $response = $this->postJson(self::ENDPOINT, $this->payload(overrides: [
            'messages' => [
                ['role' => 'user', 'content' => 'Read the file'],
                ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                    ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'read_file', 'arguments' => '{"path":"/x"}']],
                ]],
                ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => 'file-a'],
            ],
        ]));

        $response->assertOk();
        self::assertSame('chat.completion', $response->json('object'));
    }

    public function testItRejectsATrailingAssistantMessage(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->postJson(self::ENDPOINT, $this->payload(overrides: [
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'Hello'],
            ],
        ]))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_user_turn');
    }

    public function testItStreamsCitationsAsCustomFrames(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new Citation('e1', 'm1', new UrlCitation('https://example.com', 'Example'), 1001),
            new StreamEnd('e2', 'stop', new Usage(), 1002),
        ]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $chunks = array_map(static fn (array $frame): mixed => $frame['data'], $this->parseDataFrames($body));

        $citationFrames = array_values(array_filter(
            $chunks,
            static fn (mixed $chunk): bool => \is_array($chunk) && 'hawki:citation' === ($chunk['type'] ?? null),
        ));

        self::assertCount(1, $citationFrames);
        self::assertSame('https://example.com', $citationFrames[0]['citation']['url']);
    }

    public function testItSuppressesCustomFramesWhenCustomEventsAreDisabled(): void
    {
        config(['hawki.aiProxy.emit_custom_events' => false]);
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new Citation('e1', 'm1', new UrlCitation('https://example.com', 'Example'), 1001),
            new StreamEnd('e2', 'stop', new Usage(), 1002),
        ]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));

        self::assertStringNotContainsString('hawki:citation', $body);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = [], bool $stream = false): array
    {
        return array_merge([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'stream' => $stream,
        ], $overrides);
    }

    private function mockAgent(array $vendorEvents = [], ?AgentResponse $response = null): void
    {
        $agent = new FakeChatAgent($vendorEvents, $response);

        $factory = new class($agent) implements ChatAgentFactoryInterface {
            public function __construct(private readonly AgentInterface $agent)
            {
            }

            public function createAgent(AiRequest $request): ?AgentInterface
            {
                return $this->agent;
            }
        };

        $registry = new ChatAgentRegistry(new \App\Utils\Lists\LazySingletonList(
            static fn (string $factoryClass): string => 'fake_' . $factoryClass,
            static fn (string $factoryClass): ChatAgentFactoryInterface => $factory,
        ));
        $registry->declare($factory::class);

        $this->app->instance(ChatAgentRegistry::class, $registry);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: \Illuminate\Testing\TestResponse, 1: string}
     */
    private function performStreamingRequest(array $data): array
    {
        $captured = '';
        ob_start(static function (string $buffer) use (&$captured): string {
            $captured .= $buffer;

            return '';
        });
        $response = $this->postJson(self::ENDPOINT, $data);
        ob_end_clean();

        if ('' === $captured) {
            ob_start(static function (string $buffer) use (&$captured): string {
                $captured .= $buffer;

                return '';
            });
            $response->baseResponse->sendContent();
            ob_end_clean();
        }

        return [$response, $captured];
    }
}
