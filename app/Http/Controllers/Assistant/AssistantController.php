<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\Http\Controllers\Controller;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Assistants\ChatTestAssistantRequest;
use App\JsonApi\V1\Assistants\FavoriteAssistantRequest;
use App\JsonApi\V1\Assistants\FeedbackAssistantRequest;
use App\JsonApi\V1\Assistants\ReleaseAssistantRequest;
use App\Models\Ai\Tools\AiTool;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Chat\AssistantChatRunnerInterface;
use App\Services\Assistant\PromptComposer;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantController extends Controller
{
    use Actions\Destroy;
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use Actions\Update;

    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    public function created(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        Event::dispatch(new AssistantCreated($assistant));
    }

    public function updated(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        $changedKeys = array_values(array_filter(
            array_keys($assistant->getChanges()),
            fn (string $key) => $key !== 'updated_at',
        ));

        $validated = $request->validated();
        if (isset($validated['tags'])) {
            $changedKeys[] = 'tags';
        }
        if (isset($validated['ai_tools'])) {
            $changedKeys[] = 'ai_tools';
        }
        if (isset($validated['user_prompts'])) {
            $changedKeys[] = 'user_prompts';
        }

        if ($changedKeys !== []) {
            Event::dispatch(new AssistantUpdated(
                $assistant,
                $validated['version_text'] ?? null,
                $changedKeys,
            ));
        }
    }

    public function remix(AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix($assistant, request()->user());

        return DataResponse::make($remixed)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function feedback(
        FeedbackAssistantRequest $request,
        AssistantSchema $schema,
        AssistantQuery $query,
        Assistant $assistant,
    ): Responsable {
        $this->authorize('view', $assistant);

        $this->assistantService->feedback(
            $assistant,
            $request->user(),
            $request->input('data.attributes.text'),
        );

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }

    public function release(ReleaseAssistantRequest $request, AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('release', $assistant);

        $releaseStage = ReleaseStage::from($request->input('data.attributes.release_stage'));

        $assistant = $this->assistantService->release($assistant, $releaseStage);

        return DataResponse::make($assistant)
            ->withQueryParameters($query);
    }

    public function favorite(
        FavoriteAssistantRequest $request,
        AssistantSchema $schema,
        AssistantQuery $query,
        Assistant $assistant,
    ): Responsable {
        $this->authorize('favorite', $assistant);

        $this->assistantService->setFavorite(
            $assistant,
            $request->user(),
            $request->boolean('data.attributes.is_favorite'),
        );

        return DataResponse::make($assistant->fresh())
            ->withQueryParameters($query);
    }

    public function chatTest(
        ChatTestAssistantRequest $request,
        AssistantSchema $schema,
        AssistantQuery $query,
        Assistant $assistant,
    ): StreamedResponse {
        $this->authorize('view', $assistant);

        $attrs = $request->input('data.attributes');
        $runner = app(AssistantChatRunnerInterface::class);

        $assistant->load(['ai_tools', 'settingValues.setting']);

        $systemPrompt = app(PromptComposer::class)->compose($assistant);
        $messages = $this->buildMessages($attrs['input'], $systemPrompt);

        $tools = $assistant->ai_tools->map(fn (AiTool $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->inputSchema ?? ['type' => 'object', 'properties' => new \stdClass],
            ],
        ])->values()->all();

        $params = array_filter([
            'temp' => $assistant->temp,
            'top_p' => $assistant->top_p,
            'max_tokens' => $assistant->max_tokens,
        ]);

        $responseId = 'resp_'.Str::uuid()->toString();
        $model = $assistant->model;
        $createdAt = now()->timestamp;

        return response()->stream(function () use ($runner, $messages, $assistant, $tools, $params, $systemPrompt, $responseId, $model, $createdAt) {
            $seq = 0;
            $nextSeq = static function () use (&$seq): int {
                return $seq++;
            };

            $responseObject = $this->buildResponseObject($responseId, 'in_progress', $model, [], $createdAt);
            $this->writeResponseEvent('response.created', ['response' => $responseObject], $nextSeq());
            $this->writeResponseEvent('response.in_progress', ['response' => $responseObject], $nextSeq());

            $fullContent = '';
            $outputItems = [];
            $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
            $outputIndex = 0;
            $msgItemId = null;
            $inMessage = false;

            try {
                $generator = $runner->stream(
                    systemPrompt: $systemPrompt,
                    messages: $messages,
                    model: $assistant->model,
                    tools: $tools,
                    params: $params,
                );

                foreach ($generator as $chunk) {
                    match ($chunk['type']) {
                        'text_delta' => $this->handleTextDelta(
                            $chunk['content'],
                            $nextSeq,
                            $outputIndex,
                            $msgItemId,
                            $inMessage,
                            $fullContent,
                        ),
                        'tool_call' => $this->handleToolCall(
                            $chunk['content'],
                            $nextSeq,
                            $outputIndex,
                            $inMessage,
                            $msgItemId,
                            $fullContent,
                            $outputItems,
                        ),
                        'tool_result' => $this->handleToolResult(
                            $chunk['content'],
                            $nextSeq,
                            $outputIndex,
                            $outputItems,
                        ),
                        'usage' => $this->accumulateUsage($chunk['content'], $usage),
                        'status' => null,
                        default => null,
                    };
                }

                if ($inMessage) {
                    $this->closeMessageItem($nextSeq, $outputIndex, $msgItemId, $fullContent, $outputItems);
                }

                $completedResponse = $this->buildResponseObject($responseId, 'completed', $model, $outputItems, $createdAt, $usage);
                $this->writeResponseEvent('response.completed', ['response' => $completedResponse], $nextSeq());
            } catch (\Throwable $e) {
                $this->writeResponseEvent('error', [
                    'type' => 'error',
                    'code' => null,
                    'message' => $e->getMessage(),
                    'param' => null,
                ], $nextSeq());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function writeResponseEvent(string $type, array $data, int $sequenceNumber): void
    {
        $payload = array_merge($data, [
            'type' => $type,
            'sequence_number' => $sequenceNumber,
        ]);
        echo "event: {$type}\ndata: ".json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private function buildResponseObject(
        string $id,
        string $status,
        string $model,
        array $output,
        int $createdAt,
        array $usage = null,
    ): array {
        return [
            'id' => $id,
            'object' => 'response',
            'status' => $status,
            'model' => $model,
            'output' => $output,
            'usage' => $usage ?? ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0],
            'created_at' => $createdAt,
        ];
    }

    private function handleTextDelta(
        string $delta,
        callable $nextSeq,
        int &$outputIndex,
        ?string &$msgItemId,
        bool &$inMessage,
        string &$fullContent,
    ): void {
        if (! $inMessage) {
            $msgItemId = 'msg_'.Str::uuid()->toString();
            $inMessage = true;

            $this->writeResponseEvent('response.output_item.added', [
                'output_index' => $outputIndex,
                'item' => [
                    'id' => $msgItemId,
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [],
                    'status' => 'in_progress',
                ],
            ], $nextSeq());

            $this->writeResponseEvent('response.content_part.added', [
                'item_id' => $msgItemId,
                'output_index' => $outputIndex,
                'content_index' => 0,
                'part' => ['type' => 'output_text', 'text' => '', 'annotations' => []],
            ], $nextSeq());
        }

        $this->writeResponseEvent('response.output_text.delta', [
            'item_id' => $msgItemId,
            'output_index' => $outputIndex,
            'content_index' => 0,
            'delta' => $delta,
        ], $nextSeq());

        $fullContent .= $delta;
    }

    private function handleToolCall(
        array $content,
        callable $nextSeq,
        int &$outputIndex,
        bool &$inMessage,
        ?string &$msgItemId,
        string &$fullContent,
        array &$outputItems,
    ): void {
        if ($inMessage) {
            $this->closeMessageItem($nextSeq, $outputIndex, $msgItemId, $fullContent, $outputItems);
            $inMessage = false;
            $fullContent = '';
        }

        $fcItemId = 'fc_'.Str::uuid()->toString();
        $argumentsJson = is_string($content['arguments'])
            ? $content['arguments']
            : json_encode($content['arguments'], JSON_UNESCAPED_UNICODE);

        $this->writeResponseEvent('response.output_item.added', [
            'output_index' => $outputIndex,
            'item' => [
                'id' => $fcItemId,
                'type' => 'function_call',
                'call_id' => $content['tool_id'],
                'name' => $content['tool_name'],
                'arguments' => '',
                'status' => 'in_progress',
            ],
        ], $nextSeq());

        $this->writeResponseEvent('response.function_call_arguments.delta', [
            'item_id' => $fcItemId,
            'output_index' => $outputIndex,
            'delta' => $argumentsJson,
        ], $nextSeq());

        $this->writeResponseEvent('response.function_call_arguments.done', [
            'item_id' => $fcItemId,
            'name' => $content['tool_name'],
            'output_index' => $outputIndex,
            'arguments' => $argumentsJson,
        ], $nextSeq());

        $fcItem = [
            'id' => $fcItemId,
            'type' => 'function_call',
            'call_id' => $content['tool_id'],
            'name' => $content['tool_name'],
            'arguments' => $argumentsJson,
            'status' => 'completed',
        ];
        $outputItems[] = $fcItem;

        $this->writeResponseEvent('response.output_item.done', [
            'output_index' => $outputIndex,
            'item' => $fcItem,
        ], $nextSeq());

        $outputIndex++;
    }

    private function handleToolResult(
        array $content,
        callable $nextSeq,
        int &$outputIndex,
        array &$outputItems,
    ): void {
        $fcItemId = 'fc_'.Str::uuid()->toString();

        $fcItem = [
            'id' => $fcItemId,
            'type' => 'function_call',
            'call_id' => $content['tool_id'],
            'name' => $content['tool_name'],
            'result' => $content['result'],
            'status' => 'completed',
        ];
        $outputItems[] = $fcItem;

        $this->writeResponseEvent('response.output_item.added', [
            'output_index' => $outputIndex,
            'item' => $fcItem,
        ], $nextSeq());

        $this->writeResponseEvent('response.output_item.done', [
            'output_index' => $outputIndex,
            'item' => $fcItem,
        ], $nextSeq());

        $outputIndex++;
    }

    private function closeMessageItem(
        callable $nextSeq,
        int &$outputIndex,
        string $msgItemId,
        string $fullContent,
        array &$outputItems,
    ): void {
        $this->writeResponseEvent('response.output_text.done', [
            'item_id' => $msgItemId,
            'output_index' => $outputIndex,
            'content_index' => 0,
            'text' => $fullContent,
        ], $nextSeq());

        $this->writeResponseEvent('response.content_part.done', [
            'item_id' => $msgItemId,
            'output_index' => $outputIndex,
            'content_index' => 0,
            'part' => ['type' => 'output_text', 'text' => $fullContent, 'annotations' => []],
        ], $nextSeq());

        $msgItem = [
            'id' => $msgItemId,
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'output_text', 'text' => $fullContent, 'annotations' => []]],
            'status' => 'completed',
        ];
        $outputItems[] = $msgItem;

        $this->writeResponseEvent('response.output_item.done', [
            'output_index' => $outputIndex,
            'item' => $msgItem,
        ], $nextSeq());

        $outputIndex++;
    }

    private function accumulateUsage(array $content, array &$usage): void
    {
        $usage['input_tokens'] += $content['prompt_tokens'] ?? 0;
        $usage['output_tokens'] += $content['completion_tokens'] ?? 0;
        $usage['total_tokens'] = $usage['input_tokens'] + $usage['output_tokens'];
    }

    private function buildMessages(array $input, string $systemPrompt): array
    {
        $payload = [];

        if ($systemPrompt !== '') {
            $payload[] = [
                'role' => 'system',
                'content' => ['text' => $systemPrompt],
            ];
        }

        foreach ($input as $item) {
            $content = $item['content'];
            if (is_string($content)) {
                $content = ['text' => $content];
            } elseif (is_array($content) && isset($content[0]['type'])) {
                $content = ['text' => collect($content)
                    ->where('type', 'input_text')
                    ->pluck('text')
                    ->implode('')];
            }

            $payload[] = [
                'role' => $item['role'],
                'content' => $content,
            ];
        }

        return $payload;
    }
}
