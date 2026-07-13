<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Collections\SystemModelCollection;
use App\Http\Controllers\Api\V1\OpenaiResponsesController;
use App\Models\Ai\AiModel;
use App\Models\Ai\SystemModel;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\SystemModels\SystemModelRepository;
use App\Services\Ai\Streaming\AgentStreamerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Covers the generic, stateless chat exchange endpoint POST /api/openai/v1/responses.
 *
 * The endpoint supports two resolution modes:
 *  - assistant_handle provided: builds the exchange from an assistant
 *    (system prompt, tools, parameters, model).
 *  - no handle: a bare model run, or the system default model when none requested.
 */
#[CoversClass(OpenaiResponsesController::class)]
class OpenaiResponsesTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/openai/v1/responses';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'input' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ], $overrides);
    }

    private function assistantFor(User $user, array $overrides = []): Assistant
    {
        return Assistant::factory()->create(array_merge([
            'creator_id' => $user->id,
            'release_stage' => 'private',
        ], $overrides));
    }

    private function parseSseEvents(string $body): array
    {
        $events = [];
        $currentEvent = null;

        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, 'event: ')) {
                $currentEvent = ['event' => substr($line, 7), 'data' => null];
            } elseif (str_starts_with($line, 'data: ') && $currentEvent !== null) {
                $currentEvent['data'] = json_decode(substr($line, 6), true);
            } elseif ($line === '' && $currentEvent !== null) {
                $events[] = $currentEvent;
                $currentEvent = null;
            }
        }

        if ($currentEvent !== null) {
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

        if ($captured === '') {
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
     */
    private function assertValidationPointer($response, string $field): void
    {
        $response->assertStatus(422);

        $errors = $response->json('errors') ?? [];
        $found = false;

        foreach ($errors as $error) {
            if (($error['source']['pointer'] ?? null) === '/' . $field) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Expected a validation error for '/{$field}'.");
    }

    private function mockRunner(array $chunks): void
    {
        $runner = $this->createStub(AgentStreamerInterface::class);
        $runner->method('stream')->willReturnCallback(
            static function (
                string $systemPrompt,
                array $messages,
                string $model,
                array $tools,
                array $params,
                ?callable $sink,
            ) use ($chunks): \Generator {
                foreach ($chunks as $chunk) {
                    if ($sink !== null) {
                        $sink($chunk);
                    }
                }

                return (static function () use ($chunks): \Generator {
                    foreach ($chunks as $chunk) {
                        yield $chunk;
                    }
                })();
            },
        );

        $this->app->instance(AgentStreamerInterface::class, $runner);
    }

    /**
     * Stub model lookups so tests don't depend on the real AI model infrastructure.
     *
     * @param array<string, AiModel> $modelMap
     */
    private function mockModelLookup(array $modelMap): void
    {
        $this->mock(AiModelRepository::class, static function ($mock) use ($modelMap): void {
            $mock->shouldReceive('findOne')->andReturnUsing(
                static fn (mixed $id): ?AiModel => $modelMap[$id] ?? null,
            );
        });
    }

    /**
     * Stub the system default model so the no-handle / no-model fallback resolves.
     */
    private function mockDefaultSystemModel(string $modelId): void
    {
        $aiModel = new AiModel(['model_id' => $modelId]);
        $systemModel = (new SystemModel())->setRelation('model', $aiModel);

        $this->mock(SystemModelRepository::class, static function ($mock) use ($systemModel): void {
            $mock->shouldReceive('findAllFiltered')
                ->andReturn(new SystemModelCollection([$systemModel]));
        });
    }

    public function test_guest_cannot_chat(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertUnauthorized();
    }

    public function test_unknown_assistant_handle_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->assertValidationPointer(
            $this->postJson(self::ENDPOINT, $this->payload(['assistant_handle' => 'does-not-exist'])),
            'assistant_handle',
        );
    }

    public function test_cannot_chat_with_private_assistant_of_other_user(): void
    {
        $owner = User::factory()->create();
        $assistant = $this->assistantFor($owner, ['release_stage' => 'private']);

        $otherUser = User::factory()->create();
        $this->actingAsUser($otherUser);

        $this->postJson(self::ENDPOINT, $this->payload(['assistant_handle' => $assistant->handle]))
            ->assertForbidden();
    }

    public function test_returns_sse_content_type(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([['type' => 'text_delta', 'content' => 'Hi']]);

        $response = $this->postJson(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_emits_response_created_and_in_progress(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([['type' => 'text_delta', 'content' => 'Hi']]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn ($e) => $e['event'], $events);

        $this->assertContains('response.created', $eventTypes);
        $this->assertContains('response.in_progress', $eventTypes);

        $createdEvent = array_values(array_filter($events, static fn ($e) => $e['event'] === 'response.created'))[0];
        $this->assertEquals('response', $createdEvent['data']['response']['object']);
        $this->assertEquals('in_progress', $createdEvent['data']['response']['status']);
        $this->assertEquals('gpt-4', $createdEvent['data']['response']['model']);
        $this->assertStringStartsWith('resp_', $createdEvent['data']['response']['id']);
        $this->assertEquals(0, $createdEvent['data']['sequence_number']);
    }

    public function test_streams_text_deltas_with_full_hierarchy(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hel'],
            ['type' => 'text_delta', 'content' => 'lo'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn ($e) => $e['event'], $events);

        $this->assertContains('response.output_item.added', $eventTypes);
        $this->assertContains('response.content_part.added', $eventTypes);

        $deltas = array_values(array_filter($events, static fn ($e) => $e['event'] === 'response.output_text.delta'));
        $this->assertCount(2, $deltas);
        $this->assertEquals('Hel', $deltas[0]['data']['delta']);
        $this->assertEquals('lo', $deltas[1]['data']['delta']);
        $this->assertStringStartsWith('msg_', $deltas[0]['data']['item_id']);
        $this->assertSame(0, $deltas[0]['data']['output_index']);
        $this->assertSame(0, $deltas[0]['data']['content_index']);
    }

    public function test_emits_output_text_done_and_item_done(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hello'],
            ['type' => 'text_delta', 'content' => ' world'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $textDoneEvents = array_filter($events, static fn ($e) => $e['event'] === 'response.output_text.done');
        $this->assertCount(1, $textDoneEvents);
        $textDone = array_values($textDoneEvents)[0];
        $this->assertEquals('Hello world', $textDone['data']['text']);

        $itemDoneEvents = array_filter($events, static fn ($e) => $e['event'] === 'response.output_item.done');
        $this->assertCount(1, $itemDoneEvents);
        $itemDone = array_values($itemDoneEvents)[0];
        $this->assertEquals('message', $itemDone['data']['item']['type']);
        $this->assertEquals('completed', $itemDone['data']['item']['status']);
        $this->assertEquals('Hello world', $itemDone['data']['item']['content'][0]['text']);
    }

    public function test_emits_response_completed_with_output(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([['type' => 'text_delta', 'content' => 'test']]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $completedEvents = array_filter($events, static fn ($e) => $e['event'] === 'response.completed');
        $this->assertCount(1, $completedEvents);

        $completed = array_values($completedEvents)[0];
        $resp = $completed['data']['response'];
        $this->assertEquals('completed', $resp['status']);
        $this->assertEquals('gpt-4', $resp['model']);
        $this->assertCount(1, $resp['output']);
        $this->assertEquals('message', $resp['output'][0]['type']);
        $this->assertEquals('test', $resp['output'][0]['content'][0]['text']);
        $this->assertArrayHasKey('usage', $resp);
    }

    public function test_handles_error(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->method('stream')->willReturn((static function (): \Generator {
            yield ['type' => 'status', 'content' => 'starting'];
            throw new \RuntimeException('AI provider error');
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $errorEvents = array_filter($events, static fn ($e) => $e['event'] === 'error');
        $this->assertCount(1, $errorEvents);

        $errorEvent = array_values($errorEvents)[0];
        $this->assertEquals('error', $errorEvent['data']['type']);
        $this->assertStringContainsString('AI provider error', $errorEvent['data']['message']);
    }

    public function test_streams_tool_call_and_result(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([
            ['type' => 'tool_call', 'content' => ['tool_id' => 't1', 'tool_name' => 'search', 'arguments' => ['q' => 'test']]],
            ['type' => 'tool_result', 'content' => ['tool_id' => 't1', 'tool_name' => 'search', 'result' => 'found']],
            ['type' => 'text_delta', 'content' => 'Based on the results...'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(static fn ($e) => $e['event'], $events);

        $this->assertContains('response.output_item.added', $eventTypes);
        $this->assertContains('response.function_call_arguments.delta', $eventTypes);
        $this->assertContains('response.function_call_arguments.done', $eventTypes);
        $this->assertContains('response.output_item.done', $eventTypes);
        $this->assertContains('response.output_text.delta', $eventTypes);
        $this->assertContains('response.completed', $eventTypes);

        $fcArgDone = array_values(array_filter($events, static fn ($e) => $e['event'] === 'response.function_call_arguments.done'))[0];
        $this->assertEquals('search', $fcArgDone['data']['name']);
        $this->assertArrayHasKey('arguments', $fcArgDone['data']);

        $completed = array_values(array_filter($events, static fn ($e) => $e['event'] === 'response.completed'))[0];
        $output = $completed['data']['response']['output'];
        $functionCallItems = array_values(array_filter($output, static fn ($item) => $item['type'] === 'function_call'));
        $this->assertNotEmpty($functionCallItems);
        $this->assertEquals('search', $functionCallItems[0]['name']);
    }

    public function test_validates_required_input(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422);
    }

    public function test_passes_assistant_params_to_runner(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, [
            'model' => 'gpt-4',
            'system_prompt' => 'You are a helpful test assistant.',
            'temp' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 2048,
        ]);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            'You are a helpful test assistant.',
            $this->callback(static fn ($v) => is_array($v)),
            'gpt-4',
            $this->callback(static fn ($v) => is_array($v)),
            $this->callback(static function (array $params): bool {
                return isset($params['temp'], $params['top_p'], $params['max_tokens'])
                    && $params['temp'] === 0.7
                    && $params['top_p'] === 0.9
                    && $params['max_tokens'] === 2048;
            }),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );
    }

    public function test_uses_requested_model_when_allow_model_select(): void
    {
        $this->mockModelLookup(['gpt-4.1' => new AiModel(['model_id' => 'gpt-4.1'])]);

        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, [
            'model' => 'gpt-4',
            'allow_model_select' => true,
            'system_prompt' => 'test.',
            'temp' => 0.5,
            'top_p' => 0.5,
            'max_tokens' => 100,
        ]);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            'test.',
            $this->callback(static fn ($v) => is_array($v)),
            'gpt-4.1',
            $this->callback(static fn ($v) => is_array($v)),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle, 'model' => 'gpt-4.1']),
        );
    }

    public function test_allows_default_model_without_allow_model_select(): void
    {
        $this->mockModelLookup(['gpt-5' => new AiModel(['model_id' => 'gpt-5'])]);

        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, [
            'model' => 'gpt-5',
            'allow_model_select' => false,
            'system_prompt' => 'test.',
            'temp' => 0.5,
            'top_p' => 0.5,
            'max_tokens' => 100,
        ]);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            'test.',
            $this->callback(static fn ($v) => is_array($v)),
            'gpt-5',
            $this->callback(static fn ($v) => is_array($v)),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle, 'model' => 'gpt-5']),
        );
    }

    public function test_rejects_different_model_when_allow_model_select_false(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, [
            'model' => 'gpt-5',
            'allow_model_select' => false,
        ]);
        $this->actingAsUser($user);

        $this->assertValidationPointer(
            $this->postJson(
                self::ENDPOINT,
                $this->payload(['assistant_handle' => $assistant->handle, 'model' => 'gpt-4.1']),
            ),
            'model',
        );
    }

    public function test_rejects_nonexistent_model(): void
    {
        $this->mockModelLookup([]);

        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, [
            'model' => 'gpt-4',
            'allow_model_select' => true,
        ]);
        $this->actingAsUser($user);

        $this->assertValidationPointer(
            $this->postJson(
                self::ENDPOINT,
                $this->payload(['assistant_handle' => $assistant->handle, 'model' => 'nonexistent-model-12345']),
            ),
            'model',
        );
    }

    public function test_includes_usage_in_response_completed(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'test'],
            ['type' => 'usage', 'content' => ['prompt_tokens' => 15, 'completion_tokens' => 8]],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $completedEvents = array_filter($events, static fn ($e) => $e['event'] === 'response.completed');
        $completed = array_values($completedEvents)[0];
        $usage = $completed['data']['response']['usage'];

        $this->assertEquals(15, $usage['input_tokens']);
        $this->assertEquals(8, $usage['output_tokens']);
        $this->assertEquals(23, $usage['total_tokens']);
    }

    public function test_accepts_input_text_content_parts(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            $this->anything(),
            $this->callback(static function (array $messages): bool {
                $userMsg = array_values(array_filter($messages, static fn ($m) => $m['role'] === 'user'));
                $last = $userMsg[count($userMsg) - 1] ?? null;

                return $last !== null
                    && isset($last['content']['text'])
                    && $last['content']['text'] === 'Helloworld';
            }),
            'gpt-4',
            $this->anything(),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            [
                'assistant_handle' => $assistant->handle,
                'input' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'input_text', 'text' => 'Hello'],
                        ['type' => 'input_text', 'text' => 'world'],
                    ]],
                ],
            ],
        );
    }

    public function test_preserves_assistant_output_text_in_history(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            $this->anything(),
            $this->callback(static function (array $messages): bool {
                $assistantMessages = array_values(array_filter($messages, static fn ($m) => $m['role'] === 'assistant'));
                $userMessages = array_values(array_filter($messages, static fn ($m) => $m['role'] === 'user'));

                return count($assistantMessages) === 1
                    && isset($assistantMessages[0]['content']['text'])
                    && $assistantMessages[0]['content']['text'] === 'Hi there'
                    && count($userMessages) === 2
                    && isset($userMessages[1]['content']['text'])
                    && $userMessages[1]['content']['text'] === 'How are you?';
            }),
            'gpt-4',
            $this->anything(),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            [
                'assistant_handle' => $assistant->handle,
                'input' => [
                    ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Hello']]],
                    ['role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'Hi there']]],
                    ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'How are you?']]],
                ],
            ],
        );
    }

    public function test_accepts_string_input(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, ['model' => 'gpt-4']);
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            $this->anything(),
            $this->callback(static function (array $messages): bool {
                $userMsg = array_values(array_filter($messages, static fn ($m) => $m['role'] === 'user'));
                $last = $userMsg[count($userMsg) - 1] ?? null;

                return $last !== null
                    && isset($last['content']['text'])
                    && $last['content']['text'] === 'Hello world';
            }),
            'gpt-4',
            $this->anything(),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            [
                'assistant_handle' => $assistant->handle,
                'input' => 'Hello world',
            ],
        );
    }

    public function test_passes_sink_callback_to_runner(): void
    {
        $user = User::factory()->create();
        $assistant = $this->assistantFor($user, [
            'model' => 'gpt-4',
            'system_prompt' => 'test.',
            'temp' => 0.5,
            'top_p' => 0.5,
            'max_tokens' => 100,
        ]);
        $this->actingAsUser($user);

        $runnerCalled = false;
        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->willReturnCallback(
            static function (
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
            },
        );
        $this->app->instance(AgentStreamerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['assistant_handle' => $assistant->handle]),
        );

        $response->assertStatus(200);
        $this->assertTrue($runnerCalled);
        $this->assertStringContainsString('"delta":"real-time"', $body);
    }

    public function test_stateless_chat_with_model_only_uses_matching_ai_model(): void
    {
        $this->mockModelLookup(['gpt-4' => new AiModel(['model_id' => 'gpt-4'])]);

        $user = User::factory()->create();
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            $this->callback(static function (array $messages): bool {
                // no system prompt prepended, single user message
                return count($messages) === 1
                    && $messages[0]['role'] === 'user'
                    && ($messages[0]['content']['text'] ?? null) === 'Hello';
            }),
            'gpt-4',
            $this->callback(static fn ($v) => $v === []),
            $this->callback(static fn ($v) => $v === []),
        )->willReturn((static function (): \Generator {
            yield from [];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(['model' => 'gpt-4']),
        );
    }

    public function test_stateless_chat_falls_back_to_system_default_model(): void
    {
        $this->mockDefaultSystemModel('gpt-default');

        $user = User::factory()->create();
        $this->actingAsUser($user);

        $runner = $this->createMock(AgentStreamerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            '',
            $this->anything(),
            'gpt-default',
            $this->anything(),
            $this->anything(),
        )->willReturn((static function (): \Generator {
            yield ['type' => 'text_delta', 'content' => 'ok'];
        })());
        $this->app->instance(AgentStreamerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            self::ENDPOINT,
            $this->payload(),
        );

        $response->assertStatus(200);
        $this->assertStringContainsString('"model":"gpt-default"', $body);
    }
}
