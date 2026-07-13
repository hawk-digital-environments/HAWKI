<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OpenaiResponseRequest;
use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Services\Ai\AiService;
use App\Services\Ai\Streaming\AgentStreamerInterface;
use App\Services\Ai\Streaming\Sse\OpenAIResponsesAdapter;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\Assistant\AssistantPromptComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic, stateless chat exchange endpoint (OpenAI Responses-API compatible SSE)
 * at POST /api/openai/v1/responses.
 *
 * Two resolution modes, selected by the request body:
 *  - assistant handle provided: the exchange is built from the assistant
 *    (composed system prompt, attached tools, temp/top_p/max_tokens params,
 *    model from the assistant unless the client overrides it and the assistant
 *    allows model selection).
 *  - no handle: a bare model run using the requested model, or the system
 *    default chat model when none is requested. No tools, no params.
 */
class OpenaiResponsesController extends Controller
{
    public function __construct(
        private readonly AssistantPromptComposer $promptComposer,
        private readonly AiService $aiService,
    ) {
    }

    public function __invoke(
        OpenaiResponseRequest $request,
        AgentStreamerInterface $streamer,
    ): StreamedResponse {
        $assistant = $request->assistant();

        if ($assistant !== null) {
            Gate::authorize('view', $assistant);

            $assistant->load(['ai_tools', 'settingValues.setting']);

            $systemPrompt = $this->promptComposer->compose($assistant);
            $modelId = $request->input('model') ?? $assistant->model;
            $tools = $this->buildTools($assistant);
            $params = array_filter([
                'temp' => $assistant->temp,
                'top_p' => $assistant->top_p,
                'max_tokens' => $assistant->max_tokens,
            ]);
        } else {
            $modelId = $request->filled('model') ? $request->input('model') : $this->resolveDefaultModelId();
            $systemPrompt = '';
            $tools = [];
            $params = [];
        }

        $messages = $this->buildMessages(
            $request->input('input', $request->input('messages')),
            $systemPrompt,
        );

        $adapter = new OpenAIResponsesAdapter('resp_' . Str::uuid()->toString(), $modelId);

        return response()->stream(
            static function () use ($adapter, $streamer, $messages, $modelId, $tools, $params, $systemPrompt): void {
                foreach ($adapter->start() as $line) {
                    echo $line;
                    flush();
                }

                $sink = static function (array $chunk) use ($adapter): void {
                    foreach ($adapter->transform($chunk) as $line) {
                        echo $line;
                        flush();
                    }
                };

                try {
                    $generator = $streamer->stream(
                        systemPrompt: $systemPrompt,
                        messages: $messages,
                        model: $modelId,
                        tools: $tools,
                        params: $params,
                        sink: $sink,
                    );

                    foreach ($generator as $_chunk) {
                        // chunks are emitted in real time via the sink callback
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
            },
            200,
            $adapter->getHeaders(),
        );
    }

    /**
     * Resolve the system default chat model id, aborting with 422 when no
     * default is configured.
     */
    private function resolveDefaultModelId(): string
    {
        $systemModel = $this->aiService
            ->getSystemModels()
            ->findAllFiltered(modelType: WellKnownSystemModelTypes::DEFAULT)
            ->first();

        $aiModel = $systemModel?->model;

        abort_unless($aiModel !== null, 422, 'No model available.');

        return $aiModel->model_id;
    }

    /**
     * Build the OpenAI function-call tool descriptors for an assistant's tools.
     */
    private function buildTools(Assistant $assistant): array
    {
        return $assistant->ai_tools->map(static fn (AiTool $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name,
                'description' => $tool->description ?? '',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ])->values()->all();
    }

    /**
     * Normalize the request input into the runner's message shape.
     *
     * Accepts a plain string, an array of OpenAI Responses-API input items
     * (with role + content parts), or a legacy messages array. Content parts
     * of type input_text/output_text/text are flattened into a single text
     * blob; developer messages are normalized to the system role.
     *
     * @param mixed $input
     *
     * @return array<int, array{role: string, content: array{text: string}}>
     */
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

        if (!is_array($input)) {
            return $payload;
        }

        foreach ($input as $item) {
            if (!is_array($item)) {
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
}
