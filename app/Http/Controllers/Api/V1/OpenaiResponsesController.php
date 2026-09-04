<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OpenaiResponseRequest;
use App\Services\Ai\AiService;
use App\Services\Ai\Streaming\AgentStreamerInterface;
use App\Services\Ai\Streaming\Sse\OpenAIResponsesAdapter;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\Assistant\AssistantRunComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic, stateless chat exchange endpoint (OpenAI Responses-API compatible SSE)
 * at POST /api/openai/v1/responses.
 *
 * Two resolution modes, selected by the request body:
 *  - assistant handle provided: the exchange is built from the assistant via
 *    AssistantRunComposer (composed system prompt, attached tools as
 *    tool-transfer strings, temp/top_p/max_tokens params, model from the
 *    assistant unless the client overrides it and the assistant allows model
 *    selection). The handle is forwarded to the streamer so the agent factory
 *    resolves it explicitly.
 *  - no handle: a bare model run using the requested model, or the system
 *    default chat model when none is requested. No tools, no params.
 */
class OpenaiResponsesController extends Controller
{
    public function __construct(
        private readonly AssistantRunComposer $runComposer,
        private readonly AiService $aiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        OpenaiResponseRequest $request,
        AgentStreamerInterface $streamer,
    ): StreamedResponse {
        $assistant = $request->assistant();

        if (null !== $assistant) {
            Gate::authorize('view', $assistant);

            $run = $this->runComposer->compose($assistant, $request->user());

            $systemPrompt = $run->systemPrompt;
            $modelId = $request->input('model') ?? $run->modelId;
            $tools = $run->toolTransferStrings;
            $params = $run->params;
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
        $logger = $this->logger;
        $assistantHandle = $assistant?->handle;

        return response()->stream(
            static function () use ($adapter, $streamer, $messages, $modelId, $tools, $params, $logger, $assistantHandle): void {
                // Streaming a long LLM response must not be killed by PHP's
                // default max_execution_time, and a client that disconnects
                // mid-stream must terminate the upstream consumption too so
                // we don't keep paying for tokens no one will see.
                @set_time_limit(0);
                ignore_user_abort(true);

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
                        messages: $messages,
                        model: $modelId,
                        tools: $tools,
                        params: $params,
                        sink: $sink,
                        assistantHandle: $assistantHandle,
                    );

                    foreach ($generator as $_chunk) {
                        // chunks are emitted in real time via the sink callback.
                        // Stop draining the upstream the moment the client goes away.
                        if (\CONNECTION_ABORTED === connection_status() || connection_aborted()) {
                            $logger->debug('OpenAI Responses stream: client disconnected, aborting upstream consumption.');

                            // Unset the generator so its destructor closes the underlying HTTP stream.
                            unset($generator);

                            return;
                        }
                    }
                } catch (\Throwable $e) {
                    // Sanitised error event for the client; the real exception
                    // goes to the server log so credentials/internal paths
                    // cannot leak through $e->getMessage().
                    $logger->error('OpenAI Responses stream failed.', ['exception' => $e]);

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

        abort_unless(null !== $aiModel, 422, 'No model available.');

        return $aiModel->model_id;
    }

    /**
     * Normalize the request input into the runner's message shape.
     *
     * Always emits a leading system message (even when empty) so the downstream
     * legacy payload factory, which extracts the agent's instructions from the
     * first system message, always finds one. Accepts a plain string, an array
     * of OpenAI Responses-API input items (with role + content parts), or a
     * legacy messages array. Content parts of type input_text/output_text/text
     * are flattened into a single text blob; developer messages are normalized
     * to the system role.
     *
     * @return array<int, array{role: string, content: array{text: string}}>
     */
    private function buildMessages(mixed $input, string $systemPrompt): array
    {
        $payload = [
            [
                'role' => 'system',
                'content' => ['text' => $systemPrompt],
            ],
        ];

        if (\is_string($input)) {
            $payload[] = [
                'role' => 'user',
                'content' => ['text' => $input],
            ];

            return $payload;
        }

        if (!\is_array($input)) {
            return $payload;
        }

        foreach ($input as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? '';

            if (\is_string($content)) {
                $content = ['text' => $content];
            } elseif (\is_array($content) && isset($content[0]['type'])) {
                $content = ['text' => collect($content)
                    ->whereIn('type', ['input_text', 'output_text', 'text'])
                    ->pluck('text')
                    ->implode('')];
            }

            $role = $item['role'] ?? 'user';

            if ('developer' === $role) {
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
