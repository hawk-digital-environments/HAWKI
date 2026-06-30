<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantTriggerReleaseStatus;
use App\Events\AssistantUpdated;
use App\Http\Controllers\Controller;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Assistants\ChatTestAssistantRequest;
use App\Models\Ai\Tools\AiTool;
use App\Models\Assistants\Assistant;
use App\Policies\AssistantPolicy;
use App\Services\AI\Stream\OpenAIResponsesAdapter;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Chat\AssistantChatRunnerInterface;
use App\Services\Assistant\PromptComposer;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantController extends Controller
{
    use Actions\AttachRelationship;
    use Actions\Destroy;
    use Actions\DetachRelationship;
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use Actions\Update;
    use Actions\UpdateRelationship;

    private ?string $preUpdateReleaseStage = null;

    public function __construct(
        private readonly AssistantService $assistantService,
    ) {
        $this->authorizeResource(Assistant::class, 'assistant');
    }

    public function created(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        Event::dispatch(new AssistantCreated($assistant));
    }

    /**
     * Gate sensitive relationship include paths on the show endpoint. The
     * framework only authorises dedicated related/relationship URLs, not the
     * ?include query parameter, so a viewer who can `view` the assistant would
     * otherwise receive privileged children (setting values, tags, feedback,
     * review, ai_tools) inline. Authorise each requested sensitive include
     * against the assistant here.
     */
    public function read(Assistant $assistant, $request): void
    {
        $sensitive = array_merge(
            AssistantPolicy::PRIVILEGED_RELATIONSHIPS,
            AssistantPolicy::COLLABORATE_RELATIONSHIPS,
        );

        $paths = collect(explode(',', (string) $request->query('include', '')))
            ->filter()
            ->map(fn (string $path) => explode('.', $path)[0]);

        foreach ($paths as $field) {
            if (in_array($field, $sensitive, true)) {
                $this->authorize('view'.Str::studly($field), $assistant);
            }
        }
    }

    public function updating(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        $this->preUpdateReleaseStage = $assistant->release_stage;
    }

    public function updated(Assistant $assistant, AssistantRequest $request, AssistantQuery $query): void
    {
        $changedKeys = array_values(array_filter(
            array_keys($assistant->getChanges()),
            fn (string $key) => $key !== 'updated_at',
        ));

        $validated = $request->validated();
        if (isset($validated['assistant_tags'])) {
            $changedKeys[] = 'assistant_tags';
        }
        if (isset($validated['ai_tools'])) {
            $changedKeys[] = 'ai_tools';
        }

        if (isset($validated['release_stage'])) {
            $newStage = ReleaseStage::from($validated['release_stage']);
            $oldStage = $this->preUpdateReleaseStage !== null
                ? ReleaseStage::from($this->preUpdateReleaseStage)
                : null;

            if ($oldStage !== null && $newStage !== $oldStage) {
                Event::dispatch(new AssistantTriggerReleaseStatus($assistant, $oldStage, $newStage));
            }
        }

        if ($changedKeys !== []) {
            Event::dispatch(new AssistantUpdated(
                $assistant,
                null,
                $changedKeys,
            ));
        }
    }

    public function remix(AssistantSchema $schema, AssistantQuery $query, Assistant $assistant): Responsable
    {
        $this->authorize('remix', $assistant);

        $remixed = $this->assistantService->remix($assistant, request()->user());

        if ($this->shouldNotifyUpdate($assistant)) {
            Event::dispatch(new AssistantUpdated($assistant, null, ['remixed']));
        }

        return DataResponse::make($remixed)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function addFavorite(Assistant $assistant): Responsable
    {
        $this->authorize('addFavorite', $assistant);

        $this->assistantService->setFavorite($assistant, request()->user(), isFavorite: true);

        return DataResponse::make($assistant->fresh());
    }

    public function removeFavorite(Assistant $assistant): Responsable
    {
        $this->authorize('removeFavorite', $assistant);

        $this->assistantService->setFavorite($assistant, request()->user(), isFavorite: false);

        return DataResponse::make($assistant->fresh());
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

        $responseId = 'resp_'.Str::uuid()->toString();
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

        Log::debug('chatTest: request', ['model' => $model, 'msgs' => count($messages)]);

        return response()->stream(function () use ($adapter, $runner, $messages, $model, $tools, $params, $systemPrompt) {
            foreach ($adapter->start() as $line) {
                echo $line;
                flush();
            }

            $sink = function (array $chunk) use ($adapter): void {
                foreach ($adapter->transform($chunk) as $line) {
                    echo $line;
                    flush();
                }
            };

            try {
                $generator = $runner->stream(
                    systemPrompt: $systemPrompt,
                    messages: $messages,
                    model: $model,
                    tools: $tools,
                    params: $params,
                    sink: $sink,
                );
                // consume generator to trigger sink callbacks
                foreach ($generator as $_chunk) {
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
                    ->whereIn('type', ['input_text', 'output_text', 'text'])
                    ->pluck('text')
                    ->implode('')];
            }

            $role = $item['role'] ?? 'user';
            if ($role === 'developer') {
                $role = 'system';
            }

            $payload[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $payload;
    }

    private function shouldNotifyUpdate(Assistant $assistant): bool
    {
        $skipStages = [ReleaseStage::DRAFT->value, ReleaseStage::PRIVATE->value];

        return ! in_array($assistant->release_stage, $skipStages, true);
    }
}
