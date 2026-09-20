<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Chat;

use App\Http\Controllers\Api\V1\ChatController;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Chat\Factories\ChatAgentRegistry;
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
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Concerns\ParsesSseStreams;
use Tests\Feature\Api\Chat\ChatEndpointTestFixtures\FakeChatAgent;
use Tests\TestCase;

/**
 * Covers the generic chat proxy endpoint POST /api/hawki/v1/chat/{format?} with the
 * default openResponses formatter, driving the full formatter → service → normalizer
 * stack against a deterministic fake agent.
 */
#[CoversClass(ChatController::class)]
class ChatEndpointTest extends TestCase
{
    use ParsesSseStreams;
    use RefreshDatabase;
    private const string ENDPOINT = '/api/hawki/v1/chat';

    public function testGuestCannotChat(): void
    {
        $this->postJson(self::ENDPOINT, ['input' => 'Hello'])
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

    public function testItEmitsTheFullTextEventHierarchy(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new TextStart('e1', 'm1', 1001),
            new TextDelta('e2', 'm1', 'Hel', 1002),
            new TextDelta('e3', 'm1', 'lo', 1003),
            new TextEnd('e4', 'm1', 1004),
            new StreamEnd('e5', 'stop', new Usage(promptTokens: 5, completionTokens: 7), 1005),
        ]);

        [$response, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $response->assertStatus(200);

        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn (array $event): string => $event['event'], $events);

        self::assertSame('response.created', $eventTypes[0]);
        self::assertContains('response.in_progress', $eventTypes);
        self::assertContains('response.output_item.added', $eventTypes);
        self::assertContains('response.content_part.added', $eventTypes);
        self::assertContains('response.output_text.delta', $eventTypes);
        self::assertContains('response.output_text.done', $eventTypes);
        self::assertContains('response.content_part.done', $eventTypes);
        self::assertContains('response.output_item.done', $eventTypes);
        self::assertContains('response.completed', $eventTypes);

        $created = $events[0]['data'];
        self::assertSame(0, $created['sequence_number']);
        self::assertSame('resp_s1', $created['response']['id']);
        self::assertSame('gpt-4o', $created['response']['model']);

        $completed = $this->firstEvent($events, 'response.completed')['data']['response'];
        self::assertSame('completed', $completed['status']);
        self::assertSame(5, $completed['usage']['input_tokens']);
        self::assertSame(7, $completed['usage']['output_tokens']);
        self::assertSame('Hello', $completed['output'][0]['content'][0]['text']);
    }

    public function testItTerminatesTheStreamWithDone(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([new StreamEnd('e1', 'stop', new Usage(), 1000)]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));

        self::assertStringEndsWith("data: [DONE]\n\n", $body);
    }

    public function testItStreamsToolCallsAsFunctionCallItems(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new ToolCall('e1', new VendorToolCall(id: 'call_1', name: 'web_search', arguments: ['q' => 'cats']), 1000),
            new StreamEnd('e2', 'tool_calls', new Usage(), 1001),
        ]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $events = $this->parseSseEvents($body);

        $added = $this->firstEvent($events, 'response.output_item.added');
        self::assertSame('function_call', $added['data']['item']['type']);
        self::assertSame('web_search', $added['data']['item']['name']);

        $argsDone = $this->firstEvent($events, 'response.function_call_arguments.done');
        self::assertSame('{"q":"cats"}', $argsDone['data']['arguments']);
    }

    public function testItHandsClientToolCallsThroughToTheClient(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new ToolCall('e1', new VendorToolCall(id: 'fc_123', name: 'read_file', arguments: ['path' => '/tmp/x'], resultId: 'call_XYZ'), 1001),
            new \Laravel\Ai\Streaming\Events\ToolApprovalRequest('e2', collect([
                new \Laravel\Ai\Approvals\PendingApproval('fc_123', 'read_file', ['path' => '/tmp/x'], 'client tool'),
            ]), 1002),
            new StreamEnd('e3', 'tool_calls', new Usage(promptTokens: 4, completionTokens: 6), 1003),
        ]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $events = $this->parseSseEvents($body);

        $added = $this->firstEvent($events, 'response.output_item.added');
        self::assertSame('function_call', $added['data']['item']['type']);
        self::assertSame('call_XYZ', $added['data']['item']['call_id']);
        self::assertSame('read_file', $added['data']['item']['name']);

        $argsDone = $this->firstEvent($events, 'response.function_call_arguments.done');
        self::assertSame('{"path":"/tmp/x"}', $argsDone['data']['arguments']);

        $approval = $this->firstEvent($events, 'hawki:provider_tool_event');
        self::assertSame('approval_request', $approval['data']['event_type']);
        self::assertSame('read_file', $approval['data']['data'][0]['tool']);

        $completed = $this->firstEvent($events, 'response.completed');
        self::assertSame('completed', $completed['data']['response']['status']);
        self::assertSame('call_XYZ', $completed['data']['response']['output'][0]['call_id']);
        self::assertStringEndsWith("data: [DONE]\n\n", $body);
    }

    public function testItAcceptsContinuationTurnsEndingWithFunctionCallOutput(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([], new AgentResponse(
            invocationId: 'inv_2',
            text: 'The directory contains file-a.',
            usage: new Usage(promptTokens: 9, completionTokens: 5),
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        ));

        $response = $this->postJson(self::ENDPOINT, [
            'model' => 'gpt-4o',
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'List the files in /tmp'],
                ['type' => 'function_call', 'call_id' => 'call_XYZ', 'name' => 'read_file', 'arguments' => '{"path":"/tmp"}'],
                ['type' => 'function_call_output', 'call_id' => 'call_XYZ', 'output' => 'file-a'],
            ],
        ]);

        $response->assertStatus(200);
        self::assertSame('The directory contains file-a.', $response->json('output.0.content.0.text'));
    }

    public function testItEmitsCleanedCitationsAsHawkiExtensionEvents(): void
    {
        $cleaner = $this->createMock(\App\Services\ExternalContent\CitationUrlCleaner::class);
        $cleaner->method('clean')->willReturnCallback(static fn (\Laravel\Ai\Responses\Data\Citation $citation): \Laravel\Ai\Responses\Data\Citation => $citation);
        $this->app->instance(\App\Services\ExternalContent\CitationUrlCleaner::class, $cleaner);

        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new Citation('e1', 'm1', new UrlCitation('https://example.com/track?utm=1', 'Example'), 1000),
            new StreamEnd('e2', 'stop', new Usage(), 1001),
        ]);

        [, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $events = $this->parseSseEvents($body);

        $citation = $this->firstEvent($events, 'hawki:citation');
        self::assertSame('https://example.com/track?utm=1', $citation['data']['citation']['url']);
    }

    public function testItEmitsErrorAndFailedEventsOnUpstreamErrors(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new \Laravel\Ai\Streaming\Events\Error('e1', 'server_error', 'upstream exploded', false, 1001),
        ]);

        [$response, $body] = $this->performStreamingRequest($this->payload(stream: true));
        $response->assertStatus(200);

        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn (array $event): string => $event['event'], $events);

        self::assertContains('error', $eventTypes);
        self::assertContains('response.failed', $eventTypes);
        self::assertStringEndsWith("data: [DONE]\n\n", $body);
    }

    public function testItReturnsJsonForNonStreamingRequests(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([], new AgentResponse(
            invocationId: 'inv_1',
            text: 'Hello!',
            usage: new Usage(promptTokens: 2, completionTokens: 3),
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        ));

        $response = $this->postJson(self::ENDPOINT, $this->payload());

        $response->assertStatus(200);
        $data = $response->json();

        self::assertSame('response', $data['object']);
        self::assertSame('completed', $data['status']);
        self::assertSame('Hello!', $data['output'][0]['content'][0]['text']);
        self::assertSame(2, $data['usage']['input_tokens']);
    }

    public function testItRejectsStoreTrueWithOpenResponsesErrorShape(): void
    {
        $this->actingAsUser(User::factory()->create());

        $response = $this->postJson(self::ENDPOINT, $this->payload(['store' => true]));

        $response->assertStatus(400);
        self::assertSame('store_not_supported', $response->json('error.code'));
        self::assertArrayHasKey('message', $response->json('error'));
    }

    public function testItRejectsPreviousResponseId(): void
    {
        $this->actingAsUser(User::factory()->create());

        $response = $this->postJson(self::ENDPOINT, $this->payload(['previous_response_id' => 'resp_x']));

        $response->assertStatus(404);
        self::assertSame('previous_response_not_found', $response->json('error.code'));
    }

    public function testItRejectsUnknownFormatsWith400(): void
    {
        $this->actingAsUser(User::factory()->create());

        $response = $this->postJson(self::ENDPOINT . '/definitely-not-a-format', $this->payload());

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'unknown_format')
            ->assertJsonPath('error.param', 'format');
    }

    public function testItAcceptsTheExplicitOpenResponsesFormatSegment(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([new StreamEnd('e1', 'stop', new Usage(), 1000)]);

        $response = $this->postJson(self::ENDPOINT . '/openResponses', $this->payload(stream: true));

        $response->assertStatus(200);
    }

    private function mockAgent(array $vendorEvents = [], ?AgentResponse $response = null): void
    {
        $agent = new FakeChatAgent($vendorEvents, $response);

        $factory = new class($agent) implements \App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface {
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
            static fn (string $factoryClass): \App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface => $factory,
        ));
        $registry->declare($factory::class);

        $this->app->instance(ChatAgentRegistry::class, $registry);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function payload(array $overrides = [], bool $stream = false): array
    {
        return array_merge([
            'model' => 'gpt-4o',
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Hello'],
            ],
            'stream' => $stream,
        ], $overrides);
    }

    /**
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

    /**
     * @return array<int, array{event: string, data: null|array<string, mixed>}>
     */
}
