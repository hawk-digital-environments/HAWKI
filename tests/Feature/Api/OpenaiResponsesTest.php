<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Collections\SystemModelCollection;
use App\Http\Controllers\Api\V1\OpenaiResponsesController;
use App\Models\Ai\AiModel;
use App\Models\Ai\SystemModel;
use App\Models\User;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Streaming\AgentStreamerInterface;
use App\Services\Ai\SystemModels\SystemModelRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Covers the generic, stateless chat exchange endpoint POST /api/openai/v1/responses.
 *
 * The endpoint performs a bare model run using the requested model, or the
 * system default chat model when none is requested.
 */
#[CoversClass(OpenaiResponsesController::class)]
class OpenaiResponsesTest extends TestCase
{
    use RefreshDatabase;
    private const string ENDPOINT = '/api/openai/v1/responses';

    public function testGuestCannotChat(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertUnauthorized();
    }

    public function testReturnsSseContentType(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([['type' => 'text_delta', 'content' => 'Hi']]);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['model' => 'gpt-4']));

        $response->assertStatus(200);
        self::assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function testEmitsResponseCreatedAndInProgress(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([['type' => 'text_delta', 'content' => 'Hi']]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn ($e) => $e['event'], $events);

        self::assertContains('response.created', $eventTypes);
        self::assertContains('response.in_progress', $eventTypes);

        $createdEvent = array_values(array_filter($events, static fn ($e) => 'response.created' === $e['event']))[0];
        self::assertEquals('response', $createdEvent['data']['response']['object']);
        self::assertEquals('in_progress', $createdEvent['data']['response']['status']);
        self::assertEquals('gpt-4', $createdEvent['data']['response']['model']);
        self::assertStringStartsWith('resp_', $createdEvent['data']['response']['id']);
        self::assertEquals(0, $createdEvent['data']['sequence_number']);
    }

    public function testStreamsTextDeltasWithFullHierarchy(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hel'],
            ['type' => 'text_delta', 'content' => 'lo'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn ($e) => $e['event'], $events);

        self::assertContains('response.output_item.added', $eventTypes);
        self::assertContains('response.content_part.added', $eventTypes);

        $deltas = array_values(array_filter($events, static fn ($e) => 'response.output_text.delta' === $e['event']));
        self::assertCount(2, $deltas);
        self::assertEquals('Hel', $deltas[0]['data']['delta']);
        self::assertEquals('lo', $deltas[1]['data']['delta']);
        self::assertStringStartsWith('msg_', $deltas[0]['data']['item_id']);
        self::assertSame(0, $deltas[0]['data']['output_index']);
        self::assertSame(0, $deltas[0]['data']['content_index']);
    }

    public function testEmitsOutputTextDoneAndItemDone(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hello'],
            ['type' => 'text_delta', 'content' => ' world'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $textDoneEvents = array_filter($events, static fn ($e) => 'response.output_text.done' === $e['event']);
        self::assertCount(1, $textDoneEvents);
        $textDone = array_values($textDoneEvents)[0];
        self::assertEquals('Hello world', $textDone['data']['text']);

        $itemDoneEvents = array_filter($events, static fn ($e) => 'response.output_item.done' === $e['event']);
        self::assertCount(1, $itemDoneEvents);
        $itemDone = array_values($itemDoneEvents)[0];
        self::assertEquals('message', $itemDone['data']['item']['type']);
        self::assertEquals('completed', $itemDone['data']['item']['status']);
        self::assertEquals('Hello world', $itemDone['data']['item']['content'][0]['text']);
    }

    public function testEmitsResponseCompletedWithOutput(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([['type' => 'text_delta', 'content' => 'test']]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $completedEvents = array_filter($events, static fn ($e) => 'response.completed' === $e['event']);
        self::assertCount(1, $completedEvents);

        $completed = array_values($completedEvents)[0];
        $resp = $completed['data']['response'];
        self::assertEquals('completed', $resp['status']);
        self::assertEquals('gpt-4', $resp['model']);
        self::assertCount(1, $resp['output']);
        self::assertEquals('message', $resp['output'][0]['type']);
        self::assertEquals('test', $resp['output'][0]['content'][0]['text']);
        self::assertArrayHasKey('usage', $resp);
    }

    public function testHandlesError(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->method('stream')->willReturn((static function (): \Generator {
            yield ['type' => 'status', 'content' => 'starting'];

            throw new \RuntimeException('AI provider error');
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $errorEvents = array_filter($events, static fn ($e) => 'error' === $e['event']);
        self::assertCount(1, $errorEvents);

        $errorEvent = array_values($errorEvents)[0];
        self::assertEquals('error', $errorEvent['data']['type']);
        self::assertStringContainsString('AI provider error', $errorEvent['data']['message']);
    }

    public function testStreamsToolCallAndResult(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([
            ['type' => 'tool_call', 'content' => ['tool_id' => 't1', 'tool_name' => 'search', 'arguments' => ['q' => 'test']]],
            ['type' => 'tool_result', 'content' => ['tool_id' => 't1', 'tool_name' => 'search', 'result' => 'found']],
            ['type' => 'text_delta', 'content' => 'Based on the results...'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn ($e) => $e['event'], $events);

        self::assertContains('response.output_item.added', $eventTypes);
        self::assertContains('response.function_call_arguments.delta', $eventTypes);
        self::assertContains('response.function_call_arguments.done', $eventTypes);
        self::assertContains('response.output_item.done', $eventTypes);
        self::assertContains('response.output_text.delta', $eventTypes);
        self::assertContains('response.completed', $eventTypes);

        $fcArgDone = array_values(array_filter($events, static fn ($e) => 'response.function_call_arguments.done' === $e['event']))[0];
        self::assertEquals('search', $fcArgDone['data']['name']);
        self::assertArrayHasKey('arguments', $fcArgDone['data']);

        $completed = array_values(array_filter($events, static fn ($e) => 'response.completed' === $e['event']))[0];
        $output = $completed['data']['response']['output'];
        $functionCallItems = array_values(array_filter($output, static fn ($item) => 'function_call' === $item['type']));
        self::assertNotEmpty($functionCallItems);
        self::assertEquals('search', $functionCallItems[0]['name']);
    }

    public function testValidatesRequiredInput(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422);
    }

    public function testIncludesUsageInResponseCompleted(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());
        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'test'],
            ['type' => 'usage', 'content' => ['prompt_tokens' => 15, 'completion_tokens' => 8]],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $completedEvents = array_filter($events, static fn ($e) => 'response.completed' === $e['event']);
        $completed = array_values($completedEvents)[0];
        $usage = $completed['data']['response']['usage'];

        self::assertEquals(15, $usage['input_tokens']);
        self::assertEquals(8, $usage['output_tokens']);
        self::assertEquals(23, $usage['total_tokens']);
    }

    public function testAcceptsInputTextContentParts(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            self::callback(static function (array $messages): bool {
                $userMsg = array_values(array_filter($messages, static fn ($m) => 'user' === $m['role']));
                $last = $userMsg[\count($userMsg) - 1] ?? null;

                return null !== $last
                    && isset($last['content']['text'])
                    && 'Helloworld' === $last['content']['text'];
            }),
            'gpt-4',
            self::anything(),
            self::anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            [
                'model' => 'gpt-4',
                'input' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'input_text', 'text' => 'Hello'],
                        ['type' => 'input_text', 'text' => 'world'],
                    ]],
                ],
            ],
        );
    }

    public function testPreservesOutputTextInHistory(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            self::callback(static function (array $messages): bool {
                $assistantMessages = array_values(array_filter($messages, static fn ($m) => 'assistant' === $m['role']));
                $userMessages = array_values(array_filter($messages, static fn ($m) => 'user' === $m['role']));

                return \count($assistantMessages) === 1
                    && isset($assistantMessages[0]['content']['text'])
                    && 'Hi there' === $assistantMessages[0]['content']['text']
                    && \count($userMessages) === 2
                    && isset($userMessages[1]['content']['text'])
                    && 'How are you?' === $userMessages[1]['content']['text'];
            }),
            'gpt-4',
            self::anything(),
            self::anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            [
                'model' => 'gpt-4',
                'input' => [
                    ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Hello']]],
                    ['role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'Hi there']]],
                    ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'How are you?']]],
                ],
            ],
        );
    }

    public function testAcceptsStringInput(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            self::callback(static function (array $messages): bool {
                $userMsg = array_values(array_filter($messages, static fn ($m) => 'user' === $m['role']));
                $last = $userMsg[\count($userMsg) - 1] ?? null;

                return null !== $last
                    && isset($last['content']['text'])
                    && 'Hello world' === $last['content']['text'];
            }),
            'gpt-4',
            self::anything(),
            self::anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            [
                'model' => 'gpt-4',
                'input' => 'Hello world',
            ],
        );
    }

    public function testPassesSinkCallbackToRunner(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());

        $runnerCalled = false;
        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->willReturnCallback(static function (
            string $systemPrompt,
            array $messages,
            string $model,
            array $tools,
            array $params,
            ?callable $sink,
        ) use (&$runnerCalled): \Generator {
            $runnerCalled = true;
            self::assertNotNull($sink, 'Sink callback should be passed to runner');

            $sink(['type' => 'text_delta', 'content' => 'real-time']);

            return (static function (): \Generator {
                yield ['type' => 'usage', 'content' => ['prompt_tokens' => 1, 'completion_tokens' => 1]];
            })();
        },);
        $this->app->instance(AgentStreamerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );

        $response->assertStatus(200);
        self::assertTrue($runnerCalled);
        self::assertStringContainsString('"delta":"real-time"', $body);
    }

    public function testStatelessChatWithModelOnlyUsesMatchingAiModel(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $this->actingAsUser(User::factory()->create());

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            self::callback(static function (array $messages): bool {
                // no system prompt prepended, single user message
                return \count($messages) === 1
                    && 'user' === $messages[0]['role']
                    && 'Hello' === ($messages[0]['content']['text'] ?? null);
            }),
            'gpt-4',
            self::callback(static fn ($v) => [] === $v),
            self::callback(static fn ($v) => [] === $v),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );
    }

    public function testStatelessChatFallsBackToSystemDefaultModel(): void
    {
        $this->mockDefaultSystemModel('gpt-default');

        $this->actingAsUser(User::factory()->create());

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            self::anything(),
            'gpt-default',
            self::anything(),
            self::anything(),
        )->willReturn((static function (): \Generator {
            yield ['type' => 'text_delta', 'content' => 'ok'];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(),
        );

        $response->assertStatus(200);
        self::assertStringContainsString('"model":"gpt-default"', $body);
    }

    public function testRejectsNonexistentModel(): void
    {
        $this->mockModelLookup([]);

        $this->actingAsUser(User::factory()->create());

        $this->assertValidationPointer(
            $this->postJson(
                self::ENDPOINT,
                $this->payload(['model' => 'nonexistent-model-12345']),
            ),
            'model',
        );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'input' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ], $overrides);
    }

    private function parseSseEvents(string $body): array
    {
        $events = [];
        $currentEvent = null;

        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, 'event: ')) {
                $currentEvent = ['event' => mb_substr($line, 7), 'data' => null];
            } elseif (str_starts_with($line, 'data: ') && null !== $currentEvent) {
                $currentEvent['data'] = json_decode(mb_substr($line, 6), true);
            } elseif ('' === $line && null !== $currentEvent) {
                $events[] = $currentEvent;
                $currentEvent = null;
            }
        }

        if (null !== $currentEvent) {
            $events[] = $currentEvent;
        }

        return $events;
    }

    private function performStreamingRequest(string $uri, array $data): array
    {
        $captured = '';
        ob_start(static function (string $buffer) use (&$captured): string {
            $captured .= $buffer;

            return '';
        });
        $response = $this->postJson($uri, $data);
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
     * Assert the response carries a JSON:API validation error for the given field.
     *
     * The application renders ValidationExceptions in JSON:API error format
     * (errors[].source.pointer), so Laravel's default assertJsonValidationErrors
     * cannot be used.
     *
     * @param mixed $response
     */
    private function assertValidationPointer($response, string $field): void
    {
        $response->assertStatus(422);

        $errors = $response->json('errors') ?? [];
        $found = false;

        foreach ($errors as $error) {
            if ('/' . $field === ($error['source']['pointer'] ?? null)) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, "Expected a validation error for '/{$field}'.");
    }

    private function mockRunner(array $chunks): void
    {
        $runner = self::createStub(AgentStreamerInterface::class);
        $runner->method('stream')->willReturnCallback(static function (
            string $systemPrompt,
            array $messages,
            string $model,
            array $tools,
            array $params,
            ?callable $sink,
        ) use ($chunks): \Generator {
            foreach ($chunks as $chunk) {
                if (null !== $sink) {
                    $sink($chunk);
                }
            }

            return (static function () use ($chunks): \Generator {
                foreach ($chunks as $chunk) {
                    yield $chunk;
                }
            })();
        },);

        $this->app->instance(AgentStreamerInterface::class, $runner);
    }

    /**
     * Stub model lookups so tests don't depend on the real AI model infrastructure.
     *
     * @param array<string, AiModel> $modelMap
     */
    private function mockModelLookup(array $modelMap): void
    {
        $repository = $this->createMock(AiModelRepository::class);
        $repository->method('findOne')->willReturnCallback(static fn (mixed $id): ?AiModel => $modelMap[$id] ?? null);

        $this->app->instance(AiModelRepository::class, $repository);
    }

    /**
     * Stub the system default model so the no-model fallback resolves.
     */
    private function mockDefaultSystemModel(string $modelId): void
    {
        $aiModel = new AiModel(['model_id' => $modelId]);
        $systemModel = (new SystemModel())->setRelation('model', $aiModel);

        $repository = $this->createMock(SystemModelRepository::class);
        $repository->method('findAllFiltered')
            ->willReturn(new SystemModelCollection([$systemModel]));

        $this->app->instance(SystemModelRepository::class, $repository);
    }
}
