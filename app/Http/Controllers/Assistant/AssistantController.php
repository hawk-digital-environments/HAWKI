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
use App\Services\AI\Stream\OpenAIResponsesAdapter;
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
        $assistantId,
        $tail = null,
    ): StreamedResponse {
        $assistant = Assistant::findOrFail((int) $assistantId);
        $this->authorize('view', $assistant);

        $assistant->load(['ai_tools', 'settingValues.setting']);

        $model = $request->filled('model') ? $request->input('model') : $assistant->model;

        $responseId = 'resp_' . Str::uuid()->toString();
        $adapter = new OpenAIResponsesAdapter($responseId, $model);
        $runner = app(AssistantChatRunnerInterface::class);
        $systemPrompt = app(PromptComposer::class)->compose($assistant);
        $messages = $this->buildMessages($request->input('input', $request->input('messages')), $systemPrompt);

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

        return response()->stream(function () use ($adapter, $runner, $messages, $assistant, $tools, $params, $systemPrompt, $model) {
            foreach ($adapter->start() as $line) {
                echo $line;
                flush();
            }

            try {
                $generator = $runner->stream(
                    systemPrompt: $systemPrompt,
                    messages: $messages,
                    model: $model,
                    tools: $tools,
                    params: $params,
                );

                $chunkCount = 0;
                foreach ($generator as $chunk) {
                    $chunkCount++;
                    foreach ($adapter->transform($chunk) as $line) {
                        echo $line;
                        flush();
                    }
                }

                if ($chunkCount === 0) {
                    foreach ($adapter->transform(['type' => 'text_delta', 'content' => "The model returned an empty response. This may be because the model does not support tool/function calling. Try an assistant with no tools configured, or use a model that supports function calling."]) as $line) {
                        echo $line;
                        flush();
                    }
                }
            } catch (\Throwable $e) {
                foreach ($adapter->error($e) as $line) {
                    echo $line;
                    flush();
                }

                return;
            }

            foreach ($adapter->end() as $line) {
                echo $line;
                flush();
            }
        }, 200, $adapter->getHeaders());
    }

    private function buildMessages(mixed $input, string $systemPrompt): array
    {
        $payload = [];

        if ($systemPrompt !== '') {
            $payload[] = [
                'role' => 'system',
                'content' => ['text' => $systemPrompt],
            ];
        }

        if (is_string($input)) {
            $payload[] = [
                'role' => 'user',
                'content' => ['text' => $input],
            ];

            return $payload;
        }

        if (! is_array($input)) {
            return $payload;
        }

        foreach ($input as $item) {
            if (! is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? '';

            if (is_string($content)) {
                $content = ['text' => $content];
            } elseif (is_array($content) && isset($content[0]['type'])) {
                $content = ['text' => collect($content)
                    ->where('type', 'input_text')
                    ->pluck('text')
                    ->implode('')];
            }

            $payload[] = [
                'role' => $item['role'] ?? 'user',
                'content' => $content,
            ];
        }

        return $payload;
    }
}
