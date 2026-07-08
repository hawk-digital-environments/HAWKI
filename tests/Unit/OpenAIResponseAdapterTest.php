<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AI\Stream\OpenAIResponsesAdapter;
use Hamcrest\MatcherAssert;
use Hamcrest\Matchers;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Unit tests for the OpenAI Responses API adapter.
 *
 * @doesNotPerformAssertions
 */
class OpenAIResponseAdapterTest extends TestCase
{
    private function makeAdapter(string $id = 'resp_test', string $model = 'gpt-4', ?int $createdAt = null): OpenAIResponsesAdapter
    {
        return new OpenAIResponsesAdapter($id, $model, $createdAt ?? 1718000000);
    }

    private function collect(iterable $lines): array
    {
        return iterator_to_array($lines, false);
    }

    /**
     * Parse raw SSE output into {event, data} pairs.
     *
     * @return array<array{event: string, data: array}>
     */
    private function parseSse(array $lines): array
    {
        $events = [];
        foreach ($lines as $line) {
            if (preg_match('/^event: (\S+)\ndata: (.*)$/s', trim($line), $m)) {
                $events[] = ['event' => $m[1], 'data' => json_decode($m[2], true, 512, JSON_THROW_ON_ERROR)];
            }
        }

        return $events;
    }

    // ── start() ────────────────────────────────────────────────

    public function test_start_emits_response_created_and_in_progress(): void
    {
        $events = $this->parseSse($this->collect($this->makeAdapter('resp_test', 'gpt-4', 1718000000)->start()));

        MatcherAssert::assertThat($events, Matchers::contains(
            Matchers::allOf(Matchers::hasEntry('event', Matchers::equalTo('response.created')), Matchers::hasEntry('data', Matchers::allOf(
                Matchers::hasEntry('type', Matchers::equalTo('response.created')),
                Matchers::hasEntry('sequence_number', Matchers::equalTo(0)),
                Matchers::hasEntry('response', Matchers::allOf(
                    Matchers::hasEntry('id', Matchers::equalTo('resp_test')),
                    Matchers::hasEntry('object', Matchers::equalTo('response')),
                    Matchers::hasEntry('status', Matchers::equalTo('in_progress')),
                    Matchers::hasEntry('model', Matchers::equalTo('gpt-4')),
                    Matchers::hasEntry('output', Matchers::equalTo([])),
                    Matchers::hasEntry('created_at', Matchers::equalTo(1718000000)),
                )),
            ))),
            Matchers::allOf(Matchers::hasEntry('event', Matchers::equalTo('response.in_progress')), Matchers::hasEntry('data', Matchers::allOf(
                Matchers::hasEntry('sequence_number', Matchers::equalTo(1)),
            ))),
        ));
    }

    // ── text_delta ─────────────────────────────────────────────

    public function test_single_text_delta_emits_output_item_added_content_part_added_and_delta(): void
    {
        $events = $this->parseSse($this->collect(
            $this->makeAdapter()->transform(['type' => 'text_delta', 'content' => 'Hi'])
        ));

        MatcherAssert::assertThat($events, Matchers::contains(
            Matchers::allOf(
                Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')),
                Matchers::hasEntry('data', Matchers::allOf(
                    Matchers::hasEntry('output_index', Matchers::equalTo(0)),
                    Matchers::hasEntry('item', Matchers::allOf(
                        Matchers::hasEntry('type', Matchers::equalTo('message')),
                        Matchers::hasEntry('role', Matchers::equalTo('assistant')),
                        Matchers::hasEntry('status', Matchers::equalTo('in_progress')),
                        Matchers::hasKey('id'),
                    )),
                )),
            ),
            Matchers::allOf(
                Matchers::hasEntry('event', Matchers::equalTo('response.content_part.added')),
                Matchers::hasEntry('data', Matchers::allOf(
                    Matchers::hasEntry('content_index', Matchers::equalTo(0)),
                    Matchers::hasEntry('part', Matchers::allOf(
                        Matchers::hasEntry('type', Matchers::equalTo('output_text')),
                        Matchers::hasEntry('text', Matchers::equalTo('')),
                    )),
                )),
            ),
            Matchers::allOf(
                Matchers::hasEntry('event', Matchers::equalTo('response.output_text.delta')),
                Matchers::hasEntry('data', Matchers::allOf(
                    Matchers::hasEntry('delta', Matchers::equalTo('Hi')),
                    Matchers::hasEntry('content_index', Matchers::equalTo(0)),
                )),
            ),
        ));
    }

    public function test_multiple_text_deltas_only_emit_add_events_once(): void
    {
        $adapter = $this->makeAdapter();
        $events = $this->parseSse($this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'Hel'])));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(3));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')));

        $events = $this->parseSse($this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'lo'])));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(1));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.output_text.delta')));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'delta'), Matchers::equalTo('lo'));
    }

    public function test_text_deltas_accumulate_full_content(): void
    {
        $adapter = $this->makeAdapter();
        $this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'Hel']));
        $this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'lo']));

        $events = $this->parseSse($this->collect($adapter->end()));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.output_text.done')));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'text'), Matchers::equalTo('Hello'));
    }

    // ── tool_call ──────────────────────────────────────────────

    public function test_tool_call_emits_output_item_added_function_call_delta_done_and_item_done(): void
    {
        $events = $this->parseSse($this->collect($this->makeAdapter()->transform([
            'type' => 'tool_call',
            'content' => ['tool_id' => 'call_123', 'tool_name' => 'search', 'arguments' => ['q' => 'test']],
        ])));

        MatcherAssert::assertThat(count($events), Matchers::equalTo(4));
        MatcherAssert::assertThat($events[0], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')),
            Matchers::hasEntry('data', Matchers::hasEntry('item', Matchers::allOf(
                Matchers::hasEntry('type', Matchers::equalTo('function_call')),
                Matchers::hasEntry('call_id', Matchers::equalTo('call_123')),
                Matchers::hasEntry('name', Matchers::equalTo('search')),
                Matchers::hasEntry('status', Matchers::equalTo('in_progress')),
            ))),
        ));
        MatcherAssert::assertThat($events[1], Matchers::hasEntry('event', Matchers::equalTo('response.function_call_arguments.delta')));
        MatcherAssert::assertThat(Arr::get($events[1]['data'], 'delta'), Matchers::equalTo('{"q":"test"}'));
        MatcherAssert::assertThat($events[2], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('response.function_call_arguments.done')),
            Matchers::hasEntry('data', Matchers::hasEntry('name', Matchers::equalTo('search'))),
        ));
        MatcherAssert::assertThat($events[3], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('response.output_item.done')),
            Matchers::hasEntry('data', Matchers::hasEntry('item', Matchers::hasEntry('status', Matchers::equalTo('completed')))),
        ));
    }

    public function test_tool_call_with_string_arguments(): void
    {
        $events = $this->parseSse($this->collect($this->makeAdapter()->transform([
            'type' => 'tool_call',
            'content' => ['tool_id' => 'call_456', 'tool_name' => 'bash', 'arguments' => '{"cmd":"ls"}'],
        ])));
        MatcherAssert::assertThat(Arr::get($events[1]['data'], 'delta'), Matchers::equalTo('{"cmd":"ls"}'));
        MatcherAssert::assertThat(Arr::get($events[2]['data'], 'arguments'), Matchers::equalTo('{"cmd":"ls"}'));
    }

    // ── tool_result ────────────────────────────────────────────

    public function test_tool_result_emits_output_item_added_and_done_with_result(): void
    {
        $events = $this->parseSse($this->collect($this->makeAdapter()->transform([
            'type' => 'tool_result',
            'content' => ['tool_id' => 'call_789', 'tool_name' => 'search', 'result' => '42 results found'],
        ])));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(2));
        MatcherAssert::assertThat($events[0], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')),
            Matchers::hasEntry('data', Matchers::hasEntry('item', Matchers::allOf(
                Matchers::hasEntry('type', Matchers::equalTo('function_call')),
                Matchers::hasEntry('call_id', Matchers::equalTo('call_789')),
                Matchers::hasEntry('result', Matchers::equalTo('42 results found')),
                Matchers::hasEntry('status', Matchers::equalTo('completed')),
            ))),
        ));
        MatcherAssert::assertThat($events[1], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.done')));
    }

    // ── text_delta then tool_call ──────────────────────────────

    public function test_text_delta_then_tool_call_closes_message_before_function_call(): void
    {
        $adapter = $this->makeAdapter();
        $this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'pre-tool']));
        $events = $this->parseSse($this->collect($adapter->transform([
            'type' => 'tool_call',
            'content' => ['tool_id' => 't1', 'tool_name' => 'fetch', 'arguments' => []],
        ])));
        $types = array_column($events, 'event');
        MatcherAssert::assertThat($types, Matchers::equalTo([
            'response.output_text.done',
            'response.content_part.done',
            'response.output_item.done',
            'response.output_item.added',
            'response.function_call_arguments.delta',
            'response.function_call_arguments.done',
            'response.output_item.done',
        ]));
    }

    // ── end() ──────────────────────────────────────────────────

    public function test_end_emits_response_completed_with_output_and_usage(): void
    {
        $adapter = $this->makeAdapter('resp_end');
        $this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'test']));
        $this->collect($adapter->transform(['type' => 'usage', 'content' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]));
        $events = $this->parseSse($this->collect($adapter->end()));
        $closeTypes = array_column(array_slice($events, 0, 4), 'event');
        MatcherAssert::assertThat($closeTypes, Matchers::equalTo([
            'response.output_text.done',
            'response.content_part.done',
            'response.output_item.done',
            'response.completed',
        ]));
        $completed = $events[3]['data'];
        MatcherAssert::assertThat(Arr::get($completed, 'response.status'), Matchers::equalTo('completed'));
        MatcherAssert::assertThat(Arr::get($completed, 'response.id'), Matchers::equalTo('resp_end'));
        MatcherAssert::assertThat(Arr::get($completed, 'response.output.0.type'), Matchers::equalTo('message'));
        MatcherAssert::assertThat(Arr::get($completed, 'response.usage.input_tokens'), Matchers::equalTo(10));
        MatcherAssert::assertThat(Arr::get($completed, 'response.usage.output_tokens'), Matchers::equalTo(5));
        MatcherAssert::assertThat(Arr::get($completed, 'response.usage.total_tokens'), Matchers::equalTo(15));
    }

    public function test_end_with_no_input_emits_empty_completed(): void
    {
        $events = $this->parseSse($this->collect($this->makeAdapter()->end()));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(1));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.completed')));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'response.output'), Matchers::equalTo([]));
    }

    public function test_end_includes_tool_call_items_in_output(): void
    {
        $adapter = $this->makeAdapter();
        $this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'text']));
        $this->collect($adapter->transform([
            'type' => 'tool_call',
            'content' => ['tool_id' => 'c1', 'tool_name' => 'fetch', 'arguments' => '{}'],
        ]));
        $events = $this->parseSse($this->collect($adapter->end()));
        $output = Arr::get($events[count($events) - 1]['data'], 'response.output');
        $types = array_column($output, 'type');
        MatcherAssert::assertThat($types, Matchers::equalTo(['message', 'function_call']));
    }

    // ── error() ────────────────────────────────────────────────

    public function test_error_emits_error_event(): void
    {
        $events = $this->parseSse($this->collect($this->makeAdapter()->error(new \RuntimeException('AI provider error'))));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(1));
        MatcherAssert::assertThat($events[0], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('error')),
            Matchers::hasEntry('data', Matchers::allOf(
                Matchers::hasEntry('type', Matchers::equalTo('error')),
                Matchers::hasEntry('code', Matchers::equalTo(null)),
                Matchers::hasEntry('message', Matchers::equalTo('AI provider error')),
                Matchers::hasEntry('param', Matchers::equalTo(null)),
            )),
        ));
    }

    // ── usage ──────────────────────────────────────────────────

    public function test_usage_yields_nothing_and_accumulates(): void
    {
        $adapter = $this->makeAdapter();
        MatcherAssert::assertThat(count($this->collect($adapter->transform(['type' => 'usage', 'content' => ['prompt_tokens' => 10, 'completion_tokens' => 5]]))), Matchers::equalTo(0));
        MatcherAssert::assertThat(count($this->collect($adapter->transform(['type' => 'usage', 'content' => ['prompt_tokens' => 3, 'completion_tokens' => 2]]))), Matchers::equalTo(0));
        $events = $this->parseSse($this->collect($adapter->end()));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'response.usage.input_tokens'), Matchers::equalTo(13));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'response.usage.output_tokens'), Matchers::equalTo(7));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'response.usage.total_tokens'), Matchers::equalTo(20));
    }

    // ── Dropped chunk types ────────────────────────────────────

    public function test_status_yields_nothing(): void
    {
        MatcherAssert::assertThat(count($this->collect($this->makeAdapter()->transform(['type' => 'status', 'content' => 'Executing tool...']))), Matchers::equalTo(0));
    }

    public function test_unknown_type_yields_nothing(): void
    {
        MatcherAssert::assertThat(count($this->collect($this->makeAdapter()->transform(['type' => 'unknown', 'content' => 'whatever']))), Matchers::equalTo(0));
    }

    // ── sequence numbers ───────────────────────────────────────

    public function test_sequence_numbers_increment_monotonically(): void
    {
        $adapter = $this->makeAdapter();
        $allEvents = array_merge(
            $this->parseSse($this->collect($adapter->start())),
            $this->parseSse($this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'A']))),
            $this->parseSse($this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'B']))),
            $this->parseSse($this->collect($adapter->end())),
        );
        $seqs = array_map(fn ($e) => $e['data']['sequence_number'], $allEvents);
        MatcherAssert::assertThat($seqs, Matchers::equalTo(range(0, count($seqs) - 1)));
    }

    // ── thinking / reasoning ────────────────────────────────────

    public function test_thinking_delta_emits_output_item_added_then_part_added_and_text_delta(): void
    {
        $events = $this->parseSse($this->collect(
            $this->makeAdapter()->transform(['type' => 'thinking_delta', 'content' => 'Analyzing...'])
        ));

        // 3 events: output_item.added (reasoning), reasoning_summary_part.added, reasoning_summary_text.delta
        MatcherAssert::assertThat(count($events), Matchers::equalTo(3));

        MatcherAssert::assertThat($events[0], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')),
            Matchers::hasEntry('data', Matchers::hasEntry('item', Matchers::allOf(
                Matchers::hasEntry('type', Matchers::equalTo('reasoning')),
                Matchers::hasKey('id'),
            ))),
        ));

        MatcherAssert::assertThat($events[1], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_part.added')));

        MatcherAssert::assertThat($events[2], Matchers::allOf(
            Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_text.delta')),
            Matchers::hasEntry('data', Matchers::hasEntry('delta', Matchers::equalTo('Analyzing...'))),
        ));
    }

    public function test_multiple_thinking_deltas_only_emit_add_events_once(): void
    {
        $adapter = $this->makeAdapter();
        $events = $this->parseSse($this->collect($adapter->transform(['type' => 'thinking_delta', 'content' => 'First'])));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(3));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')));

        $events = $this->parseSse($this->collect($adapter->transform(['type' => 'thinking_delta', 'content' => 'Second'])));
        MatcherAssert::assertThat(count($events), Matchers::equalTo(1));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_text.delta')));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'delta'), Matchers::equalTo('Second'));
    }

    public function test_thinking_delta_then_text_delta_closes_thinking_before_message(): void
    {
        $adapter = $this->makeAdapter();
        $this->collect($adapter->transform(['type' => 'thinking_delta', 'content' => 'reasoning...']));
        $events = $this->parseSse($this->collect($adapter->transform(['type' => 'text_delta', 'content' => 'Hello'])));

        // Close thinking: text.done, part.done, item.done (reasoning), then message
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_text.done')));
        MatcherAssert::assertThat($events[1], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_part.done')));
        MatcherAssert::assertThat($events[2], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.done')));
        MatcherAssert::assertThat($events[3], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')));
    }

    public function test_thinking_delta_then_tool_call_closes_thinking_before_function_call(): void
    {
        $adapter = $this->makeAdapter();
        $this->collect($adapter->transform(['type' => 'thinking_delta', 'content' => 'I should use bash.']));
        $events = $this->parseSse($this->collect($adapter->transform([
            'type' => 'tool_call',
            'content' => ['tool_id' => 't1', 'tool_name' => 'bash', 'arguments' => []],
        ])));
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_text.done')));
        MatcherAssert::assertThat($events[1], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_part.done')));
        MatcherAssert::assertThat($events[2], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.done')));
        MatcherAssert::assertThat($events[3], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.added')));
    }

    public function test_end_closes_open_thinking_with_item_done(): void
    {
        $adapter = $this->makeAdapter();
        $this->collect($adapter->transform(['type' => 'thinking_delta', 'content' => 'Final thoughts.']));
        $events = $this->parseSse($this->collect($adapter->end()));

        // text.done, part.done, item.done (reasoning), then completed
        MatcherAssert::assertThat($events[0], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_text.done')));
        MatcherAssert::assertThat(Arr::get($events[0]['data'], 'text'), Matchers::equalTo('Final thoughts.'));
        MatcherAssert::assertThat($events[1], Matchers::hasEntry('event', Matchers::equalTo('response.reasoning_summary_part.done')));
        MatcherAssert::assertThat($events[2], Matchers::hasEntry('event', Matchers::equalTo('response.output_item.done')));
        MatcherAssert::assertThat($events[3], Matchers::hasEntry('event', Matchers::equalTo('response.completed')));
    }

    // ── getHeaders() ───────────────────────────────────────────

    public function test_get_headers_returns_sse_headers(): void
    {
        MatcherAssert::assertThat($this->makeAdapter()->getHeaders(), Matchers::equalTo([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]));
    }

    // ── INTEGRATION: all input types in sequence ───────────────

    public function test_full_conversation_with_all_input_types(): void
    {
        $adapter = $this->makeAdapter('resp_integration', 'gpt-4', 1718000000);
        $allLines = [];

        foreach ($adapter->start() as $line) {
            $allLines[] = $line;
        }

        foreach ($adapter->transform(['type' => 'thinking_delta', 'content' => 'I should search for this.']) as $line) {
            $allLines[] = $line;
        }
        foreach ($adapter->transform(['type' => 'thinking_delta', 'content' => ' Let me do that.']) as $line) {
            $allLines[] = $line;
        }

        foreach (['Let ', 'me ', 'search!'] as $t) {
            foreach ($adapter->transform(['type' => 'text_delta', 'content' => $t]) as $line) {
                $allLines[] = $line;
            }
        }

        foreach ($adapter->transform(['type' => 'status', 'content' => 'Executing...']) as $line) {
            $allLines[] = $line;
        }

        foreach ($adapter->transform([
            'type' => 'tool_call',
            'content' => ['tool_id' => 'call_search', 'tool_name' => 'search', 'arguments' => ['query' => 'hello']],
        ]) as $line) {
            $allLines[] = $line;
        }

        foreach ($adapter->transform([
            'type' => 'tool_result',
            'content' => ['tool_id' => 'call_search', 'tool_name' => 'search', 'result' => '3 results'],
        ]) as $line) {
            $allLines[] = $line;
        }

        foreach (['Found ', '3 results.'] as $t) {
            foreach ($adapter->transform(['type' => 'text_delta', 'content' => $t]) as $line) {
                $allLines[] = $line;
            }
        }

        foreach ($adapter->transform(['type' => 'usage', 'content' => ['prompt_tokens' => 20, 'completion_tokens' => 15]]) as $line) {
            $allLines[] = $line;
        }

        foreach ($adapter->end() as $line) {
            $allLines[] = $line;
        }

        $events = $this->parseSse($allLines);

        MatcherAssert::assertThat(array_column($events, 'event'), Matchers::equalTo([
            'response.created',
            'response.in_progress',
            'response.output_item.added',
            'response.reasoning_summary_part.added',
            'response.reasoning_summary_text.delta',
            'response.reasoning_summary_text.delta',
            'response.reasoning_summary_text.done',
            'response.reasoning_summary_part.done',
            'response.output_item.done',
            'response.output_item.added',
            'response.content_part.added',
            'response.output_text.delta',
            'response.output_text.delta',
            'response.output_text.delta',
            'response.output_text.done',
            'response.content_part.done',
            'response.output_item.done',
            'response.output_item.added',
            'response.function_call_arguments.delta',
            'response.function_call_arguments.done',
            'response.output_item.done',
            'response.output_item.added',
            'response.output_item.done',
            'response.output_item.added',
            'response.content_part.added',
            'response.output_text.delta',
            'response.output_text.delta',
            'response.output_text.done',
            'response.content_part.done',
            'response.output_item.done',
            'response.completed',
        ]));

        $seqs = array_map(fn ($e) => $e['data']['sequence_number'], $events);
        MatcherAssert::assertThat($seqs, Matchers::equalTo(range(0, count($seqs) - 1)));

        $completed = $events[count($events) - 1]['data'];
        MatcherAssert::assertThat(Arr::get($completed, 'response.id'), Matchers::equalTo('resp_integration'));
        MatcherAssert::assertThat(Arr::get($completed, 'response.status'), Matchers::equalTo('completed'));
        MatcherAssert::assertThat(Arr::get($completed, 'response.usage.input_tokens'), Matchers::equalTo(20));
        MatcherAssert::assertThat(Arr::get($completed, 'response.usage.output_tokens'), Matchers::equalTo(15));
        MatcherAssert::assertThat(Arr::get($completed, 'response.usage.total_tokens'), Matchers::equalTo(35));

        $output = Arr::get($completed, 'response.output');
        MatcherAssert::assertThat(count($output), Matchers::equalTo(4));
        MatcherAssert::assertThat(Arr::get($output, '0.type'), Matchers::equalTo('message'));
        MatcherAssert::assertThat(Arr::get($output, '0.content.0.text'), Matchers::equalTo('Let me search!'));
        MatcherAssert::assertThat(Arr::get($output, '1.type'), Matchers::equalTo('function_call'));
        MatcherAssert::assertThat(Arr::get($output, '1.name'), Matchers::equalTo('search'));
        MatcherAssert::assertThat(Arr::get($output, '2.type'), Matchers::equalTo('function_call'));
        MatcherAssert::assertThat(Arr::get($output, '2.result'), Matchers::equalTo('3 results'));
        MatcherAssert::assertThat(Arr::get($output, '3.type'), Matchers::equalTo('message'));
        MatcherAssert::assertThat(Arr::get($output, '3.content.0.text'), Matchers::equalTo('Found 3 results.'));
    }
}
