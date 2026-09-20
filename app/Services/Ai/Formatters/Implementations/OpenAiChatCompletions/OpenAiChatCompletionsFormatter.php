<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions;

use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\Configs\GenerationConfig;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatConfig;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatType;
use App\Services\Ai\Chat\Values\Configs\StreamConfig;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\Message;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\AudioAsset;
use App\Services\Ai\Chat\Values\Parts\AudioPart;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\ToolResultPart;
use App\Services\Ai\Chat\Values\Tools\ToolCallConfig;
use App\Services\Ai\Chat\Values\Tools\ToolChoice;
use App\Services\Ai\Chat\Values\Tools\ToolChoiceMode;
use App\Services\Ai\Chat\Values\Tools\ToolDefinition;
use App\Services\Ai\Formatters\Concerns\ParsesHawkiExtensions;
use App\Services\Ai\Formatters\Contracts\FormatterInterface;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\InvalidInputItemException;
use App\Services\Ai\Formatters\Exceptions\InvalidRequestBodyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The OpenAI Chat Completions wire format (format key `openai`) — the dialect every
 * LiteLLM-compatible client speaks.
 *
 * `messages[]` maps onto the IR history with roles preserved; `system`/`developer`
 * messages are hoisted into {@see AiRequest::$systemInstruction} like the Open
 * Responses formatter hoists system items. Tool calls arrive/leave as JSON strings
 * (`function.arguments`), addressed by `index` in streams. `n`, `logit_bias` and
 * `user` have no IR counterpart and ride {@see AiRequest::$providerExtensions}.
 */
readonly class OpenAiChatCompletionsFormatter implements FormatterInterface
{
    use ParsesHawkiExtensions;
    public const string KEY = 'openai';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function parseRequest(Request $request): AiRequest
    {
        $body = $request->json()->all();

        if (!\is_array($body) || [] === $body) {
            throw InvalidRequestBodyException::forUnparseableBody();
        }

        $messagesInput = $body['messages'] ?? null;

        if (!\is_array($messagesInput) || [] === $messagesInput) {
            throw InvalidRequestBodyException::forMissingInput();
        }

        $messages = [];
        $systemParts = [];

        foreach ($messagesInput as $message) {
            $this->parseMessageItem($message, $messages, $systemParts);
        }

        $lastMessage = $messages[array_key_last($messages)] ?? null;

        if (!$lastMessage instanceof UserMessage && !$lastMessage instanceof ToolMessage) {
            throw InvalidInputItemException::forMissingTrailingUserMessage();
        }

        if (\is_string($body['instructions'] ?? null) && '' !== $body['instructions']) {
            $systemParts[] = TextPart::from($body['instructions']);
        }

        return new AiRequest(
            model: \is_string($body['model'] ?? null) && '' !== $body['model'] ? $body['model'] : null,
            messages: $messages,
            systemInstruction: [] !== $systemParts ? $systemParts : null,
            tools: $this->parseTools($body),
            toolChoice: $this->parseToolChoice($body),
            toolConfig: $this->parseToolConfig($body),
            generation: $this->parseGenerationConfig($body),
            responseFormat: $this->parseResponseFormat($body),
            stream: new StreamConfig(
                enabled: true === ($body['stream'] ?? false),
                includeUsage: true === (($body['stream_options'] ?? [])['include_usage'] ?? false),
            ),
            providerExtensions: $this->parseProviderExtensions($body),
            hawkiExtensions: self::parseHawkiExtensions($body),
            formatKey: self::KEY,
        );
    }

    public function formatResponse(AiResponse $response): JsonResponse
    {
        $message = ['role' => 'assistant'];

        $text = $response->message->text();
        $message['content'] = '' !== $text ? $text : null;

        $reasoning = $this->reasoningText($response->message);
        $message['reasoning_content'] = null !== $reasoning ? $reasoning : null;

        $toolCalls = $response->message->toolCalls();

        $message['tool_calls'] = [] !== $toolCalls
            ? array_map(static fn (ToolCallPart $part): array => [
                'id' => $part->toolCallId,
                'type' => 'function',
                'function' => [
                    'name' => $part->toolName,
                    'arguments' => (string) json_encode($part->toolInput, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
                ],
            ], $toolCalls)
            : null;

        $message['refusal'] = $this->refusalText($response->message);

        $usage = $response->usage;

        return new JsonResponse([
            'id' => self::ensureChunkIdPrefix($response->id),
            'object' => 'chat.completion',
            'created' => $response->created,
            'model' => $response->model,
            'system_fingerprint' => $response->systemFingerprint,
            'choices' => [
                [
                    'index' => 0,
                    'message' => $message,
                    'finish_reason' => self::finishReason($response->finishReason->reason),
                ],
            ],
            'usage' => [
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
                'total_tokens' => $usage->totalTokens ?? 0,
            ],
        ]);
    }

    public function formatStream(iterable $events): StreamedResponse
    {
        $context = new OpenAiChatCompletionsStreamContext(emitCustomEvents: (bool) config('hawki.aiProxy.emit_custom_events', true));

        return response()->stream(
            static function () use ($events, $context): void {
                $emit = static function (array $chunk): void {
                    echo \sprintf("data: %s\n\n", json_encode($chunk, \JSON_UNESCAPED_UNICODE));
                    flush();
                };

                try {
                    foreach ($events as $event) {
                        foreach ($context->transform($event) as $chunk) {
                            $emit($chunk);
                        }
                    }
                } catch (\Throwable $e) {
                    foreach ($context->error($e) as $chunk) {
                        $emit($chunk);
                    }

                    echo "data: [DONE]\n\n";
                    flush();

                    return;
                }

                foreach ($context->end() as $chunk) {
                    $emit($chunk);
                }

                echo "data: [DONE]\n\n";
                flush();
            },
            200,
            $this->getStreamHeaders(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getStreamHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    public function formatError(FormatterRequestException $exception): Response
    {
        return new JsonResponse([
            'error' => [
                'message' => $exception->getMessage(),
                'type' => $exception->errorType(),
                'param' => $exception->param(),
                'code' => $exception->errorCode(),
            ],
        ], $exception->httpStatus());
    }

    public static function finishReason(FinishReasonType $reason): string
    {
        return match ($reason) {
            FinishReasonType::STOP => 'stop',
            FinishReasonType::LENGTH => 'length',
            FinishReasonType::TOOL_CALLS => 'tool_calls',
            FinishReasonType::CONTENT_FILTER, FinishReasonType::REFUSAL => 'content_filter',
            FinishReasonType::ERROR, FinishReasonType::CANCELLED => 'stop',
        };
    }

    public static function ensureChunkIdPrefix(string $id): string
    {
        return str_starts_with($id, 'chatcmpl-') ? $id : 'chatcmpl-' . $id;
    }

    /**
     * @param array<int, Message>  $messages
     * @param array<int, TextPart> $systemParts
     */
    private function parseMessageItem(mixed $item, array &$messages, array &$systemParts): void
    {
        if (!\is_array($item)) {
            throw InvalidInputItemException::forMalformedItem($item);
        }

        $role = (string) ($item['role'] ?? '');

        if ('system' === $role || 'developer' === $role) {
            foreach ($this->parseContentParts($item['content'] ?? '') as $part) {
                if ($part instanceof TextPart) {
                    $systemParts[] = $part;
                }
            }

            return;
        }

        if ('tool' === $role) {
            $messages[] = new ToolMessage(parts: [new ToolResultPart(
                toolCallId: (string) ($item['tool_call_id'] ?? ''),
                result: \is_string($item['content'] ?? null) ? $item['content'] : '',
            )]);

            return;
        }

        if ('assistant' === $role) {
            $parts = [];

            foreach ($this->parseContentParts($item['content'] ?? '') as $part) {
                if ($part instanceof TextPart) {
                    $parts[] = $part;
                }
            }

            if (\is_string($item['reasoning_content'] ?? null) && '' !== $item['reasoning_content']) {
                $parts[] = new ReasoningPart(reasoning: $item['reasoning_content']);
            }

            foreach (\is_array($item['tool_calls'] ?? null) ? $item['tool_calls'] : [] as $toolCall) {
                if (!\is_array($toolCall)) {
                    continue;
                }

                $function = \is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];

                $parts[] = new ToolCallPart(
                    toolCallId: (string) ($toolCall['id'] ?? ''),
                    toolName: (string) ($function['name'] ?? ''),
                    toolInput: $this->decodeArguments($function['arguments'] ?? '{}'),
                );
            }

            $messages[] = new AssistantMessage(parts: [] !== $parts ? $parts : [TextPart::from('')]);

            return;
        }

        if ('user' === $role) {
            $messages[] = new UserMessage(parts: $this->userParts($this->parseContentParts($item['content'] ?? '')));

            return;
        }

        throw InvalidInputItemException::forUnknownType('' !== $role ? $role : 'unknown');
    }

    /**
     * @return array<int, \App\Services\Ai\Chat\Values\Parts\ContentPart>
     */
    private function parseContentParts(mixed $content): array
    {
        if (\is_string($content)) {
            return [TextPart::from($content)];
        }

        if (null === $content) {
            return [];
        }

        if (!\is_array($content)) {
            return [TextPart::from('')];
        }

        $parts = [];

        foreach ($content as $part) {
            if (!\is_array($part)) {
                continue;
            }

            $partType = $part['type'] ?? null;

            if ('text' === $partType) {
                $parts[] = TextPart::from((string) ($part['text'] ?? ''));

                continue;
            }

            if ('image_url' === $partType && \is_array($part['image_url'] ?? null)) {
                $parts[] = new ImagePart(
                    imageUrl: isset($part['image_url']['url']) ? (string) $part['image_url']['url'] : null,
                    detail: isset($part['image_url']['detail']) ? (string) $part['image_url']['detail'] : null,
                );

                continue;
            }

            if ('input_audio' === $partType && \is_array($part['input_audio'] ?? null)) {
                $parts[] = new AudioPart(audioData: new AudioAsset(
                    data: (string) ($part['input_audio']['data'] ?? ''),
                    mediaType: 'audio/' . (string) ($part['input_audio']['format'] ?? 'wav'),
                ), );

                continue;
            }

            if ('file' === $partType && \is_array($part['file'] ?? null)) {
                $parts[] = new FilePart(
                    fileUrl: isset($part['file']['file_id']) ? (string) $part['file']['file_id'] : null,
                    fileName: isset($part['file']['filename']) ? (string) $part['filename'] : null,
                );
            }
        }

        return [] !== $parts ? $parts : [TextPart::from('')];
    }

    /**
     * @param array<int, \App\Services\Ai\Chat\Values\Parts\ContentPart> $parts
     *
     * @return array<int, AudioPart|FilePart|ImagePart|TextPart>
     */
    private function userParts(array $parts): array
    {
        return array_values(array_filter(
            $parts,
            static fn (mixed $part): bool => $part instanceof TextPart
                || $part instanceof ImagePart
                || $part instanceof FilePart
                || $part instanceof AudioPart,
        ));
    }

    /**
     * @return null|array<int, ToolDefinition>
     */
    private function parseTools(array $body): ?array
    {
        if (!\is_array($body['tools'] ?? null)) {
            return null;
        }

        $tools = [];

        foreach ($body['tools'] as $tool) {
            if (!\is_array($tool) || 'function' !== ($tool['type'] ?? null)) {
                continue;
            }

            $function = \is_array($tool['function'] ?? null) ? $tool['function'] : [];

            $tools[] = new ToolDefinition(
                name: (string) ($function['name'] ?? ''),
                description: (string) ($function['description'] ?? ''),
                parameters: \is_array($function['parameters'] ?? null) ? $function['parameters'] : [],
                metadata: \array_key_exists('strict', $function) ? ['strict' => (bool) $function['strict']] : null,
            );
        }

        return [] !== $tools ? $tools : null;
    }

    private function parseToolChoice(array $body): ?ToolChoice
    {
        $toolChoice = $body['tool_choice'] ?? null;

        if (null === $toolChoice) {
            return null;
        }

        if ('none' === $toolChoice) {
            return new ToolChoice(mode: ToolChoiceMode::NONE);
        }

        if ('required' === $toolChoice) {
            return new ToolChoice(mode: ToolChoiceMode::ANY);
        }

        if ('auto' === $toolChoice) {
            return ToolChoice::auto();
        }

        if (\is_array($toolChoice) && 'function' === ($toolChoice['type'] ?? null)) {
            return new ToolChoice(
                mode: ToolChoiceMode::TOOL,
                toolName: (string) (($toolChoice['function'] ?? [])['name'] ?? ''),
            );
        }

        return null;
    }

    private function parseToolConfig(array $body): ?ToolCallConfig
    {
        if (!isset($body['parallel_tool_calls']) || !\is_bool($body['parallel_tool_calls'])) {
            return null;
        }

        return new ToolCallConfig(disableParallel: !$body['parallel_tool_calls']);
    }

    private function parseGenerationConfig(array $body): ?GenerationConfig
    {
        $maxTokens = $body['max_completion_tokens'] ?? $body['max_tokens'] ?? null;

        $stop = null;

        if (\is_string($body['stop'] ?? null)) {
            $stop = [$body['stop']];
        } elseif (\is_array($body['stop'] ?? null)) {
            $stop = array_values(array_filter($body['stop'], 'is_string'));
        }

        $logitBias = \is_array($body['logit_bias'] ?? null) ? $body['logit_bias'] : null;

        $config = new GenerationConfig(
            temperature: isset($body['temperature']) && is_numeric($body['temperature']) ? (float) $body['temperature'] : null,
            topP: isset($body['top_p']) && is_numeric($body['top_p']) ? (float) $body['top_p'] : null,
            maxTokens: is_numeric($maxTokens) ? (int) $maxTokens : null,
            frequencyPenalty: isset($body['frequency_penalty']) && is_numeric($body['frequency_penalty']) ? (float) $body['frequency_penalty'] : null,
            presencePenalty: isset($body['presence_penalty']) && is_numeric($body['presence_penalty']) ? (float) $body['presence_penalty'] : null,
            stopSequences: $stop,
            logitBias: $logitBias,
            seed: isset($body['seed']) && is_numeric($body['seed']) ? (int) $body['seed'] : null,
            logprobs: isset($body['logprobs']) && \is_bool($body['logprobs']) ? $body['logprobs'] : null,
            topLogprobs: isset($body['top_logprobs']) && is_numeric($body['top_logprobs']) ? (int) $body['top_logprobs'] : null,
        );

        return null !== $config->temperature
        || null !== $config->topP
        || null !== $config->maxTokens
        || null !== $config->frequencyPenalty
        || null !== $config->presencePenalty
        || null !== $config->stopSequences
        || null !== $config->logitBias
        || null !== $config->seed
        || null !== $config->logprobs
        || null !== $config->topLogprobs
            ? $config
            : null;
    }

    private function parseResponseFormat(array $body): ?ResponseFormatConfig
    {
        $format = $body['response_format'] ?? null;

        if (!\is_array($format)) {
            return null;
        }

        $type = (string) ($format['type'] ?? 'text');

        if ('json_object' === $type) {
            return new ResponseFormatConfig(type: ResponseFormatType::JSON_OBJECT);
        }

        if ('json_schema' === $type && \is_array($format['json_schema'] ?? null)) {
            $schema = $format['json_schema'];

            return new ResponseFormatConfig(
                type: ResponseFormatType::JSON_SCHEMA,
                jsonSchema: \is_array($schema['schema'] ?? null) ? $schema['schema'] : null,
                name: isset($schema['name']) ? (string) $schema['name'] : null,
                strict: isset($schema['strict']) ? (bool) $schema['strict'] : null,
            );
        }

        return new ResponseFormatConfig(type: ResponseFormatType::tryFrom($type) ?? ResponseFormatType::TEXT);
    }

    /**
     * Fields without an IR counterpart are accepted for format alignment and parked,
     * tagged by this formatter (D8): never silently ignored, never executed.
     *
     * @return null|array<string, mixed>
     */
    private function parseProviderExtensions(array $body): ?array
    {
        $extensions = [];

        foreach (['n', 'logit_bias', 'user'] as $passthrough) {
            if (\array_key_exists($passthrough, $body)) {
                $extensions[$passthrough] = $body[$passthrough];
            }
        }

        return [] !== $extensions ? $extensions : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArguments(mixed $arguments): array
    {
        if (\is_array($arguments)) {
            return $arguments;
        }

        if (\is_string($arguments) && '' !== $arguments) {
            $decoded = json_decode($arguments, true);

            return \is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function reasoningText(AssistantMessage $message): ?string
    {
        $reasoning = '';

        foreach ($message->parts as $part) {
            if ($part instanceof ReasoningPart && null !== $part->reasoning) {
                $reasoning .= $part->reasoning;
            }
        }

        return '' !== $reasoning ? $reasoning : null;
    }

    private function refusalText(AssistantMessage $message): ?string
    {
        $refusal = '';

        foreach ($message->parts as $part) {
            if ($part instanceof \App\Services\Ai\Chat\Values\Parts\RefusalPart) {
                $refusal .= $part->refusal;
            }
        }

        return '' !== $refusal ? $refusal : null;
    }
}
