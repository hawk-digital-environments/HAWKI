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
                    'messages' => [
                        ['role' => 'user', 'content' => ['text' => 'Hello']],
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

    public function test_chat_test_streams_text_deltas(): void
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

        $deltas = array_filter($events, fn ($e) => $e['event'] === 'text_delta');
        $this->assertCount(2, $deltas);
        $this->assertEquals('Hel', $events[1]['data']);
        $this->assertEquals('lo', $events[2]['data']);
    }

    public function test_chat_test_sends_message_event_with_full_content(): void
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

        $messageEvents = array_filter($events, fn ($e) => $e['event'] === 'message');
        $this->assertCount(1, $messageEvents);

        $messageEvent = array_values($messageEvents)[0];
        $this->assertEquals('messages', $messageEvent['data']['type']);
        $this->assertEquals('completed', $messageEvent['data']['attributes']['status']);
        $this->assertEquals('gpt-4', $messageEvent['data']['attributes']['model']);
        $this->assertEquals('Hello world', $messageEvent['data']['attributes']['content']);
    }

    public function test_chat_test_sends_stream_start_and_end(): void
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

        $eventTypes = array_map(fn ($e) => $e['event'], $events);
        $this->assertContains('stream_start', $eventTypes);
        $this->assertContains('stream_end', $eventTypes);
        $this->assertContains('message', $eventTypes);

        $startEvent = array_values(array_filter($events, fn ($e) => $e['event'] === 'stream_start'))[0];
        $this->assertEquals('gpt-4', $startEvent['data']['model']);

        $endEvent = array_values(array_filter($events, fn ($e) => $e['event'] === 'stream_end'))[0];
        $this->assertEquals('stop', $endEvent['data']['reason']);
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

        $failedEvents = array_filter($events, fn ($e) => $e['event'] === 'stream_failed');
        $this->assertCount(1, $failedEvents);

        $failedEvent = array_values($failedEvents)[0];
        $this->assertStringContainsString('AI provider error', $failedEvent['data']['message']);
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

        $this->assertContains('tool_call', $eventTypes);
        $this->assertContains('tool_result', $eventTypes);
        $this->assertContains('text_delta', $eventTypes);

        $toolCallEvents = array_values(array_filter($events, fn ($e) => $e['event'] === 'tool_call'));
        $this->assertEquals('t1', $toolCallEvents[0]['data']['tool_id']);
        $this->assertEquals('search', $toolCallEvents[0]['data']['tool_name']);
        $this->assertEquals(['q' => 'test'], $toolCallEvents[0]['data']['arguments']);

        $toolResultEvents = array_values(array_filter($events, fn ($e) => $e['event'] === 'tool_result'));
        $this->assertEquals('t1', $toolResultEvents[0]['data']['tool_id']);
        $this->assertEquals('found', $toolResultEvents[0]['data']['result']);
    }

    public function test_chat_test_validates_required_messages(): void
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

    public function test_chat_test_passes_system_prompt_to_runner(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'model' => 'gpt-4',
            'system_prompt' => 'You are a helpful test assistant.',
        ]);
        Sanctum::actingAs($user);

        $runner = $this->createMock(AssistantChatRunnerInterface::class);
        $runner->expects($this->once())->method('stream')->with(
            'You are a helpful test assistant.',
            $this->callback(fn ($v) => is_array($v)),
            'gpt-4',
            $this->callback(fn ($v) => is_array($v)),
            $this->callback(fn ($v) => is_array($v)),
        )->willReturn((function () {
            yield from [];
        })());
        $this->app->instance(AssistantChatRunnerInterface::class, $runner);

        $this->performStreamingRequest(
            "/api/assistants/{$assistant->id}/actions/chat-test",
            $this->createChatTestPayload([
                'id' => $assistant->id,
                'attributes' => [
                    'tools' => [['type' => 'function']],
                    'params' => ['temperature' => 0.7],
                ],
            ]),
        );
    }
}
