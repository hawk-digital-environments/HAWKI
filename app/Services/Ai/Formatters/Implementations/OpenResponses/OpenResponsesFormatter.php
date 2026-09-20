<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\OpenResponses;

use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\Configs\GenerationConfig;
use App\Services\Ai\Chat\Values\Configs\ReasoningConfig;
use App\Services\Ai\Chat\Values\Configs\ReasoningMode;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatConfig;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatType;
use App\Services\Ai\Chat\Values\Configs\StreamConfig;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\Message;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\AudioPart;
use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Parts\RefusalPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\ToolResultPart;
use App\Services\Ai\Chat\Values\Parts\UrlCitation;
use App\Services\Ai\Chat\Values\Tools\ToolCallConfig;
use App\Services\Ai\Chat\Values\Tools\ToolChoice;
use App\Services\Ai\Chat\Values\Tools\ToolChoiceMode;
use App\Services\Ai\Chat\Values\Tools\ToolDefinition;
use App\Services\Ai\Formatters\Contracts\FormatterInterface;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\InvalidInputItemException;
use App\Services\Ai\Formatters\Exceptions\InvalidRequestBodyException;
use App\Services\Ai\Formatters\Exceptions\UnsupportedStatefulParameterException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The OpenAI Responses API / Open Responses wire format — the reference formatter and
 * the default chat format of the generic proxy.
 *
 * The IR is modelled on Open Responses, so request parsing is nearly an identity
 * mapping: `input` items become IR messages, `instructions` the system instruction,
 * `tools` IR tool definitions. HAWKI-specific request metadata (tool-transfer strings,
 * attachment UUIDs, legacy params) rides the top-level `hawki` object and becomes
 * {@see AiRequest::$hawkiExtensions}.
 *
 * HAWKI implements the **stateless subset** of Open Responses: `store: true` and
 * `previous_response_id` are rejected with 4xx, compaction and WebSocket continuation
 * are not offered.
 */
readonly class OpenResponsesFormatter implements FormatterInterface
{
    public const string KEY = 'openResponses';

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

        if (true === ($body['store'] ?? false)) {
            throw UnsupportedStatefulParameterException::forStore();
        }

        if (isset($body['previous_response_id'])) {
            throw UnsupportedStatefulParameterException::forPreviousResponseId();
        }

        $input = $body['input'] ?? null;

        if (null === $input) {
            throw InvalidRequestBodyException::forMissingInput();
        }

        $messages = [];
        $systemParts = [];

        if (\is_string($input)) {
            $messages[] = UserMessage::fromText($input);
        } elseif (\is_array($input)) {
            foreach ($input as $item) {
                $this->parseInputItem($item, $messages, $systemParts);
            }
        } else {
            throw InvalidRequestBodyException::forMissingInput();
        }

        $lastMessage = $this->lastContinuableMessage($messages);

        if (!$lastMessage instanceof UserMessage && !$lastMessage instanceof ToolMessage) {
            throw InvalidInputItemException::forMissingTrailingUserMessage();
        }

        $instructions = \is_string($body['instructions'] ?? null) ? $body['instructions'] : null;

        if (null !== $instructions && '' !== $instructions) {
            $systemParts[] = TextPart::from($instructions);
        }

        $providerExtensions = [];

        foreach (['include', 'metadata', 'service_tier', 'background', 'stream_options', 'prompt_cache_key', 'safety_identifier'] as $passthrough) {
            if (\array_key_exists($passthrough, $body)) {
                $providerExtensions[$passthrough] = $body[$passthrough];
            }
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
            stream: new StreamConfig(enabled: true === ($body['stream'] ?? false)),
            reasoning: $this->parseReasoningConfig($body),
            providerExtensions: [] !== $providerExtensions ? $providerExtensions : null,
            hawkiExtensions: $this->parseHawkiExtensions($body),
            formatKey: self::KEY,
        );
    }

    public function formatResponse(AiResponse $response): JsonResponse
    {
        $finish = $response->finishReason;
        $status = 'completed';
        $incompleteDetails = null;

        if (FinishReasonType::LENGTH === $finish->reason) {
            $status = 'incomplete';
            $incompleteDetails = ['reason' => 'max_output_tokens'];
        } elseif (FinishReasonType::CONTENT_FILTER === $finish->reason) {
            $status = 'incomplete';
            $incompleteDetails = ['reason' => 'content_filter'];
        }

        return new JsonResponse(OpenResponsesResource::resource(
            id: str_starts_with($response->id, 'resp_') ? $response->id : 'resp_' . $response->id,
            model: $response->model,
            createdAt: $response->created,
            output: $this->buildOutputItems($response),
            status: $status,
            incompleteDetails: $incompleteDetails,
            usage: $response->usage,
        ));
    }

    public function formatStream(iterable $events): StreamedResponse
    {
        $context = new OpenResponsesStreamContext();

        return response()->stream(
            static function () use ($events, $context): void {
                $emit = static function (array $frame): void {
                    echo \sprintf("event: %s\ndata: %s\n\n", $frame['event'], json_encode($frame['data'], \JSON_UNESCAPED_UNICODE));
                    flush();
                };

                try {
                    foreach ($events as $event) {
                        foreach ($context->transform($event) as $frame) {
                            $emit($frame);
                        }
                    }
                } catch (\Throwable $e) {
                    foreach ($context->error($e) as $frame) {
                        $emit($frame);
                    }

                    echo "data: [DONE]\n\n";
                    flush();

                    return;
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
        return new JsonResponse(
            [
                'error' => [
                    'message' => $exception->getMessage(),
                    'type' => $exception->errorType(),
                    'param' => $exception->param(),
                    'code' => $exception->errorCode(),
                ],
            ],
            $exception->httpStatus(),
        );
    }

    /**
     * @param array<int, Message>  $messages
     * @param array<int, TextPart> $systemParts
     */
    private function parseInputItem(mixed $item, array &$messages, array &$systemParts): void
    {
        if (!\is_array($item)) {
            throw InvalidInputItemException::forMalformedItem($item);
        }

        $type = $item['type'] ?? null;

        if (null === $type && isset($item['role'])) {
            $type = 'message';
        }

        switch ($type) {
            case 'message':
                $this->parseMessageItem($item, $messages, $systemParts);

                return;

            case 'function_call':
                $messages[] = new AssistantMessage(parts: [new ToolCallPart(
                    toolCallId: (string) ($item['call_id'] ?? ''),
                    toolName: (string) ($item['name'] ?? ''),
                    toolInput: $this->decodeArguments($item['arguments'] ?? '{}'),
                )]);

                return;

            case 'function_call_output':
                $messages[] = new ToolMessage(parts: [new ToolResultPart(
                    toolCallId: (string) ($item['call_id'] ?? ''),
                    result: $this->parseToolOutput($item['output'] ?? ''),
                )]);

                return;

            case 'reasoning':
                $summaryText = collect($item['summary'] ?? [])
                    ->filter(static fn (mixed $part): bool => \is_array($part) && 'summary_text' === ($part['type'] ?? null))
                    ->map(static fn (array $part): string => (string) ($part['text'] ?? ''))
                    ->implode('');

                $messages[] = new AssistantMessage(parts: [new ReasoningPart(
                    reasoning: '' !== $summaryText ? $summaryText : null,
                    encryptedContent: isset($item['encrypted_content']) ? (string) $item['encrypted_content'] : null,
                )]);

                return;

            case 'item_reference':
                throw InvalidInputItemException::forUnknownType('item_reference');

            default:
                if (\is_string($type) && str_starts_with($type, 'hawki:')) {
                    return;
                }

                throw InvalidInputItemException::forUnknownType(\is_string($type) ? $type : 'unknown');
        }
    }

    /**
     * The last message that can legally conclude the input: a user turn, or the
     * tool-result turn of a client-driven tool loop. Trailing reasoning-only assistant
     * items are ignored for this check.
     *
     * @param array<int, Message> $messages
     */
    private function lastContinuableMessage(array $messages): ?Message
    {
        for ($key = array_key_last($messages); null !== $key; --$key) {
            $message = $messages[$key];

            if ($message instanceof AssistantMessage && $message->text() === '' && $this->hasOnlyReasoningParts($message)) {
                continue;
            }

            return $message;
        }

        return null;
    }

    private function hasOnlyReasoningParts(AssistantMessage $message): bool
    {
        foreach ($message->parts as $part) {
            if (!$part instanceof ReasoningPart) {
                return false;
            }
        }

        return [] !== $message->parts;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, Message>  $messages
     * @param array<int, TextPart> $systemParts
     */
    private function parseMessageItem(array $item, array &$messages, array &$systemParts): void
    {
        $role = (string) ($item['role'] ?? 'user');
        $content = $item['content'] ?? '';
        $parts = $this->parseContentParts($content);

        if ('system' === $role || 'developer' === $role) {
            foreach ($parts as $part) {
                if ($part instanceof TextPart) {
                    $systemParts[] = $part;
                }
            }

            return;
        }

        $messages[] = 'assistant' === $role
            ? new AssistantMessage(parts: $this->assistantParts($parts))
            : new UserMessage(parts: $this->userParts($parts));
    }

    /**
     * @return array<int, \App\Services\Ai\Chat\Values\Parts\ContentPart>
     */
    private function parseContentParts(mixed $content): array
    {
        if (\is_string($content)) {
            return [TextPart::from($content)];
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

            if ('input_text' === $partType || 'output_text' === $partType || 'text' === $partType || 'summary_text' === $partType) {
                $parts[] = TextPart::from((string) ($part['text'] ?? ''));

                continue;
            }

            if ('input_image' === $partType) {
                $parts[] = new ImagePart(
                    imageUrl: isset($part['image_url']) ? (string) $part['image_url'] : null,
                    detail: isset($part['detail']) ? (string) $part['detail'] : null,
                );

                continue;
            }

            if ('input_file' === $partType) {
                $parts[] = new FilePart(
                    fileUrl: isset($part['file_id']) ? (string) $part['file_id'] : null,
                    fileName: isset($part['filename']) ? (string) $part['filename'] : null,
                );
            }
        }

        return [] !== $parts ? $parts : [TextPart::from('')];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHawkiExtensions(array $body): ?array
    {
        $hawki = $body['hawki'] ?? null;

        if (!\is_array($hawki)) {
            return null;
        }

        $extensions = [];

        if (\is_array($hawki['tools'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_TOOLS] = array_values(array_filter(
                $hawki['tools'],
                static fn (mixed $tool): bool => \is_string($tool),
            ));
        }

        if (\is_array($hawki['attachments'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_ATTACHMENTS] = array_values(array_filter(
                $hawki['attachments'],
                static fn (mixed $uuid): bool => \is_string($uuid),
            ));
        }

        if (\is_array($hawki['params'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_PARAMS] = $hawki['params'];
        }

        if (true === ($hawki['broadcast'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_BROADCAST] = true;
        }

        return [] !== $extensions ? $extensions : null;
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

            $tools[] = new ToolDefinition(
                name: (string) ($tool['name'] ?? ''),
                description: (string) ($tool['description'] ?? ''),
                parameters: \is_array($tool['parameters'] ?? null) ? $tool['parameters'] : [],
            );
        }

        return $tools;
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
            return new ToolChoice(mode: ToolChoiceMode::TOOL, toolName: (string) ($toolChoice['name'] ?? ''));
        }

        return null;
    }

    private function parseToolConfig(array $body): ?ToolCallConfig
    {
        $disableParallel = null;
        $maxCalls = null;

        if (isset($body['parallel_tool_calls']) && \is_bool($body['parallel_tool_calls'])) {
            $disableParallel = !$body['parallel_tool_calls'];
        }

        if (isset($body['max_tool_calls']) && is_numeric($body['max_tool_calls'])) {
            $maxCalls = (int) $body['max_tool_calls'];
        }

        return null !== $disableParallel || null !== $maxCalls
            ? new ToolCallConfig(disableParallel: $disableParallel, maxCalls: $maxCalls)
            : null;
    }

    private function parseGenerationConfig(array $body): ?GenerationConfig
    {
        $config = new GenerationConfig(
            temperature: isset($body['temperature']) && is_numeric($body['temperature']) ? (float) $body['temperature'] : null,
            topP: isset($body['top_p']) && is_numeric($body['top_p']) ? (float) $body['top_p'] : null,
            maxTokens: isset($body['max_output_tokens']) && is_numeric($body['max_output_tokens']) ? (int) $body['max_output_tokens'] : null,
            frequencyPenalty: isset($body['frequency_penalty']) && is_numeric($body['frequency_penalty']) ? (float) $body['frequency_penalty'] : null,
            presencePenalty: isset($body['presence_penalty']) && is_numeric($body['presence_penalty']) ? (float) $body['presence_penalty'] : null,
            truncation: isset($body['truncation']) ? (string) $body['truncation'] : null,
        );

        return null !== $config->temperature
        || null !== $config->topP
        || null !== $config->maxTokens
        || null !== $config->frequencyPenalty
        || null !== $config->presencePenalty
        || null !== $config->truncation
            ? $config
            : null;
    }

    private function parseReasoningConfig(array $body): ?ReasoningConfig
    {
        $reasoning = $body['reasoning'] ?? null;

        if (!\is_array($reasoning)) {
            return null;
        }

        $mode = null;
        $effort = null;

        if (isset($reasoning['effort']) && \is_string($reasoning['effort'])) {
            if ('none' === $reasoning['effort']) {
                $mode = ReasoningMode::DISABLED;
            } else {
                $effort = \App\Services\Ai\Chat\Values\Configs\ReasoningEffort::tryFrom($reasoning['effort']);
            }
        }

        $summary = isset($reasoning['summary']) && \is_string($reasoning['summary'])
            ? \App\Services\Ai\Chat\Values\Configs\ReasoningSummary::tryFrom($reasoning['summary'])
            : null;

        return new ReasoningConfig(mode: $mode, effort: $effort, summary: $summary);
    }

    private function parseResponseFormat(array $body): ?ResponseFormatConfig
    {
        $text = $body['text'] ?? null;

        if (!\is_array($text) || !\is_array($text['format'] ?? null)) {
            return null;
        }

        $format = $text['format'];
        $type = (string) ($format['type'] ?? 'text');

        return new ResponseFormatConfig(
            type: ResponseFormatType::tryFrom($type) ?? ResponseFormatType::TEXT,
            jsonSchema: \is_array($format['schema'] ?? null) ? $format['schema'] : null,
            name: isset($format['name']) ? (string) $format['name'] : null,
            strict: isset($format['strict']) ? (bool) $format['strict'] : null,
        );
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

    /**
     * Filters parsed content parts down to the user-role part union.
     *
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
     * Filters parsed content parts down to the assistant-role part union.
     *
     * @param array<int, \App\Services\Ai\Chat\Values\Parts\ContentPart> $parts
     *
     * @return array<int, CitationPart|ReasoningPart|RefusalPart|TextPart|ToolCallPart>
     */
    private function assistantParts(array $parts): array
    {
        return array_values(array_filter(
            $parts,
            static fn (mixed $part): bool => $part instanceof TextPart
                || $part instanceof ToolCallPart
                || $part instanceof ReasoningPart
                || $part instanceof RefusalPart
                || $part instanceof CitationPart,
        ));
    }

    private function parseToolOutput(mixed $output): string
    {
        if (\is_string($output)) {
            return $output;
        }

        if (\is_array($output)) {
            $text = collect($output)
                ->filter(static fn (mixed $part): bool => \is_array($part) && isset($part['text']))
                ->map(static fn (array $part): string => (string) $part['text'])
                ->implode('');

            return $text;
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOutputItems(AiResponse $response): array
    {
        $items = [];

        foreach ($response->message->parts as $part) {
            if ($part instanceof ReasoningPart) {
                $summary = null !== $part->reasoning && '' !== $part->reasoning
                    ? [['type' => 'summary_text', 'text' => $part->reasoning]]
                    : [];

                $items[] = array_filter([
                    'id' => 'rs_' . Str::uuid()->toString(),
                    'type' => 'reasoning',
                    'summary' => $summary,
                    'encrypted_content' => $part->encryptedContent,
                ], static fn (mixed $value): bool => null !== $value);

                continue;
            }

            if ($part instanceof ToolCallPart) {
                $items[] = [
                    'id' => 'fc_' . Str::uuid()->toString(),
                    'type' => 'function_call',
                    'call_id' => $part->toolCallId,
                    'name' => $part->toolName,
                    'arguments' => (string) json_encode($part->toolInput, \JSON_UNESCAPED_UNICODE),
                    'status' => 'completed',
                ];

                continue;
            }

            if ($part instanceof \App\Services\Ai\Chat\Values\Parts\CitationPart) {
                $items[] = $this->buildCitationItem($part);

                continue;
            }

            if ($part instanceof \App\Services\Ai\Chat\Values\Parts\TextPart) {
                $items[] = [
                    'id' => 'msg_' . Str::uuid()->toString(),
                    'type' => 'message',
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => [['type' => 'output_text', 'text' => $part->text, 'annotations' => []]],
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCitationItem(\App\Services\Ai\Chat\Values\Parts\CitationPart $part): array
    {
        $urlCitation = $part->urlCitation ?? new UrlCitation();

        return [
            'id' => 'cit_' . Str::uuid()->toString(),
            'type' => 'hawki:citation',
            'status' => 'completed',
            'citation' => [
                'url' => $urlCitation->url,
                'title' => $urlCitation->title,
                'start_index' => $urlCitation->startIndex,
                'end_index' => $urlCitation->endIndex,
            ],
        ];
    }
}
