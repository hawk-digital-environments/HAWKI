<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\JsonApi\V1\Assistants\AssistantQuery;
use App\JsonApi\V1\Assistants\AssistantRequest;
use App\JsonApi\V1\Assistants\AssistantSchema;
use App\JsonApi\V1\Assistants\ChatTestAssistantRequest;
use App\JsonApi\V1\Assistants\FavoriteAssistantRequest;
use App\JsonApi\V1\Assistants\FeedbackAssistantRequest;
use App\JsonApi\V1\Assistants\ReleaseAssistantRequest;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\Chat\AssistantChatRunnerInterface;
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
        $messages = $this->buildMessages($attrs['messages'], $assistant);

        return response()->stream(function () use ($runner, $messages, $assistant, $attrs) {
            $this->writeEvent('stream_start', ['model' => $assistant->model]);

            $fullContent = '';

            try {
                $generator = $runner->stream(
                    systemPrompt: $assistant->system_prompt ?? '',
                    messages: $messages,
                    model: $assistant->model,
                    tools: $attrs['tools'] ?? [],
                    params: $attrs['params'] ?? [],
                );

                foreach ($generator as $chunk) {
                    $this->writeEvent($chunk['type'], $chunk['content']);

                    if ($chunk['type'] === 'text_delta') {
                        $fullContent .= $chunk['content'];
                    }
                }

                $this->writeEvent('message', [
                    'type' => 'messages',
                    'id' => (string) Str::uuid(),
                    'attributes' => [
                        'content' => $fullContent,
                        'model' => $assistant->model,
                        'status' => 'completed',
                    ],
                ]);

                $this->writeEvent('stream_end', ['reason' => 'stop']);
            } catch (\Throwable $e) {
                $this->writeEvent('stream_failed', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function writeEvent(string $event, mixed $data): void
    {
        echo "event: {$event}\ndata: ".json_encode($data)."\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private function buildMessages(array $messages, Assistant $assistant): array
    {
        $payload = [];

        if (! empty($assistant->system_prompt)) {
            $payload[] = [
                'role' => 'system',
                'content' => ['text' => $assistant->system_prompt],
            ];
        }

        foreach ($messages as $msg) {
            $payload[] = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? ['text' => ''],
            ];
        }

        return $payload;
    }
}
