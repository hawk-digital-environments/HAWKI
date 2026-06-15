<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Chat\AssistantChatRunnerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTestTest extends TestCase
{
    use RefreshDatabase;

    private function createChatTestPayload(array $overrides = []): array
    {
        return [
            'data' => [
                'type' => 'assistants',
                'id' => (string) ($overrides['id'] ?? '1'),
                'attributes' => array_merge([
                    'input' => [
                        ['role' => 'user', 'content' => 'Hello'],
                    ],
                ], $overrides['attributes'] ?? []),
            ],
        ];
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
        ob_start(function (string $buffer) use (&$captured): string {
            $captured .= $buffer;

            return '';
        });
        $response = $this->jsonApi('post', $uri, $data);
        ob_end_clean();

        if ($captured === '') {
            ob_start(function (string $buffer) use (&$captured): string {
                $captured .= $buffer;

                return '';
            });
            $response->baseResponse->sendContent();
            ob_end_clean();
        }

        return [$response, $captured];
    }

    private function mockRunner(array $chunks): void
    {
        $runner = $this->createStub(AssistantChatRunnerInterface::class);
        $runner->method('stream')->willReturn((function () use ($chunks) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })());

        $this->app->instance(AssistantChatRunnerInterface::class, $runner);
    }

    public function test_guest_cannot_chat_test(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/chat-test", $this->createChatTestPayload(['id' => $assistant->id]))
            ->assertUnauthorized();
    }

    public function test_cannot_chat_test_nonexistent_assistant(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants/999/actions/chat-test', $this->createChatTestPayload(['id' => 999]))
            ->assertNotFound();
    }

    public function test_cannot_chat_test_private_assistant_of_other_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/chat-test", $this->createChatTestPayload(['id' => $assistant->id]))
            ->assertForbidden();
    }

    public function test_chat_test_returns_sse_content_type(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hi'],
        ]);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/chat-test", $this->createChatTestPayload(['id' => $assistant->id]));

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_chat_test_emits_response_created_and_in_progress(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hi'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(fn ($e) => $e['event'], $events);

        $this->assertContains('response.created', $eventTypes);
        $this->assertContains('response.in_progress', $eventTypes);

        $createdEvent = array_values(array_filter($events, fn ($e) => $e['event'] === 'response.created'))[0];
        $this->assertEquals('response', $createdEvent['data']['response']['object']);
        $this->assertEquals('in_progress', $createdEvent['data']['response']['status']);
        $this->assertEquals('gpt-4', $createdEvent['data']['response']['model']);
        $this->assertStringStartsWith('resp_', $createdEvent['data']['response']['id']);
        $this->assertEquals(0, $createdEvent['data']['sequence_number']);
    }

    public function test_chat_test_streams_text_deltas_with_full_hierarchy(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hel'],
            ['type' => 'text_delta', 'content' => 'lo'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(fn ($e) => $e['event'], $events);

        $this->assertContains('response.output_item.added', $eventTypes);
        $this->assertContains('response.content_part.added', $eventTypes);

        $deltas = array_values(array_filter($events, fn ($e) => $e['event'] === 'response.output_text.delta'));
        $this->assertCount(2, $deltas);
        $this->assertEquals('Hel', $deltas[0]['data']['delta']);
        $this->assertEquals('lo', $deltas[1]['data']['delta']);
        $this->assertStringStartsWith('msg_', $deltas[0]['data']['item_id']);
        $this->assertSame(0, $deltas[0]['data']['output_index']);
        $this->assertSame(0, $deltas[0]['data']['content_index']);
    }

    public function test_chat_test_emits_output_text_done_and_item_done(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'Hello'],
            ['type' => 'text_delta', 'content' => ' world'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $textDoneEvents = array_filter($events, fn ($e) => $e['event'] === 'response.output_text.done');
        $this->assertCount(1, $textDoneEvents);
        $textDone = array_values($textDoneEvents)[0];
        $this->assertEquals('Hello world', $textDone['data']['text']);

        $itemDoneEvents = array_filter($events, fn ($e) => $e['event'] === 'response.output_item.done');
        $this->assertCount(1, $itemDoneEvents);
        $itemDone = array_values($itemDoneEvents)[0];
        $this->assertEquals('message', $itemDone['data']['item']['type']);
        $this->assertEquals('completed', $itemDone['data']['item']['status']);
        $this->assertEquals('Hello world', $itemDone['data']['item']['content'][0]['text']);
    }

    public function test_chat_test_emits_response_completed_with_output(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'test'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $completedEvents = array_filter($events, fn ($e) => $e['event'] === 'response.completed');
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

    public function test_chat_test_handles_error(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $runner = $this->createStub(AssistantChatRunnerInterface::class);
        $runner->method('stream')->willThrowException(new \RuntimeException('AI provider error'));
        $this->app->instance(AssistantChatRunnerInterface::class, $runner);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $errorEvents = array_filter($events, fn ($e) => $e['event'] === 'error');
        $this->assertCount(1, $errorEvents);

        $errorEvent = array_values($errorEvents)[0];
        $this->assertEquals('error', $errorEvent['data']['type']);
        $this->assertStringContainsString('AI provider error', $errorEvent['data']['message']);
    }

    public function test_chat_test_streams_tool_call_and_result(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'tool_call', 'content' => ['tool_id' => 't1', 'tool_name' => 'search', 'arguments' => ['q' => 'test']]],
            ['type' => 'tool_result', 'content' => ['tool_id' => 't1', 'tool_name' => 'search', 'result' => 'found']],
            ['type' => 'text_delta', 'content' => 'Based on the results...'],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);
        $eventTypes = array_map(fn ($e) => $e['event'], $events);

        $this->assertContains('response.output_item.added', $eventTypes);
        $this->assertContains('response.function_call_arguments.delta', $eventTypes);
        $this->assertContains('response.function_call_arguments.done', $eventTypes);
        $this->assertContains('response.output_item.done', $eventTypes);
        $this->assertContains('response.output_text.delta', $eventTypes);
        $this->assertContains('response.completed', $eventTypes);

        $fcArgDone = array_values(array_filter($events, fn ($e) => $e['event'] === 'response.function_call_arguments.done'))[0];
        $this->assertEquals('search', $fcArgDone['data']['name']);
        $this->assertArrayHasKey('arguments', $fcArgDone['data']);

        $completed = array_values(array_filter($events, fn ($e) => $e['event'] === 'response.completed'))[0];
        $output = $completed['data']['response']['output'];
        $functionCallItems = array_values(array_filter($output, fn ($item) => $item['type'] === 'function_call'));
        $this->assertNotEmpty($functionCallItems);
        $this->assertEquals('search', $functionCallItems[0]['name']);
    }

    public function test_chat_test_validates_required_input(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/chat-test", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [],
            ],
        ])
            ->assertStatus(422);
    }

    public function test_chat_test_passes_assistant_params_to_runner(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'model' => 'gpt-4',
            'system_prompt' => 'You are a helpful test assistant.',
            'temp' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 2048,
        ]);
        Sanctum::actingAs($user);

        $runner = $this->createMock(AssistantChatRunnerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            'You are a helpful test assistant.',
            $this->callback(fn ($v) => is_array($v)),
            'gpt-4',
            $this->callback(fn ($v) => is_array($v)),
            $this->callback(function (array $params) {
                return isset($params['temp'], $params['top_p'], $params['max_tokens'])
                    && $params['temp'] === 0.7
                    && $params['top_p'] === 0.9
                    && $params['max_tokens'] === 2048;
            }),
        )->willReturn((function () {
            yield from [];
        })());
        $this->app->instance(AssistantChatRunnerInterface::class, $runner);

        $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );
    }

    public function test_chat_test_includes_usage_in_response_completed(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $this->mockRunner([
            ['type' => 'text_delta', 'content' => 'test'],
            ['type' => 'usage', 'content' => ['prompt_tokens' => 15, 'completion_tokens' => 8]],
        ]);

        [$response, $body] = $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload(['id' => $assistant->id]),
        );

        $response->assertStatus(200);
        $events = $this->parseSseEvents($body);

        $completedEvents = array_filter($events, fn ($e) => $e['event'] === 'response.completed');
        $completed = array_values($completedEvents)[0];
        $usage = $completed['data']['response']['usage'];

        $this->assertEquals(15, $usage['input_tokens']);
        $this->assertEquals(8, $usage['output_tokens']);
        $this->assertEquals(23, $usage['total_tokens']);
    }

    public function test_chat_test_accepts_input_text_content_parts(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => 'private',
            'model' => 'gpt-4',
        ]);
        Sanctum::actingAs($user);

        $runner = $this->createMock(AssistantChatRunnerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            $this->anything(),
            $this->callback(function (array $messages) {
                $userMsg = array_filter($messages, fn ($m) => $m['role'] === 'user');
                $last = array_values($userMsg);
                $last = $last[count($last) - 1] ?? null;

                return $last !== null
                    && isset($last['content']['text'])
                    && $last['content']['text'] === 'Helloworld';
            }),
            'gpt-4',
            $this->anything(),
            $this->anything(),
        )->willReturn((function () {
            yield from [];
        })());
        $this->app->instance(AssistantChatRunnerInterface::class, $runner);

        $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            [
                'data' => [
                    'type' => 'assistants',
                    'id' => (string) $assistant->id,
                    'attributes' => [
                        'input' => [
                            ['role' => 'user', 'content' => [
                                ['type' => 'input_text', 'text' => 'Hello'],
                                ['type' => 'input_text', 'text' => 'world'],
                            ]],
                        ],
                    ],
                ],
            ],
        );
    }
}
