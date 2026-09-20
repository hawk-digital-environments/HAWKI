<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Factories\Implementations;

use App\Models\Ai\AiModel;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Implementations\Chat\StructuredChatAgent;
use App\Services\Ai\Agents\Implementations\Chat\ChatToolResolver;
use App\Services\Ai\Agents\Utils\AlternatingMessageHistory;
use App\Services\Ai\Agents\Utils\MessageMetaBlocks;
use App\Services\Ai\Agents\Utils\UserMessageAttachments;
use App\Services\Ai\AiService;
use App\Services\Ai\Chat\Exceptions\ChatAgentNotResolvedException;
use App\Services\Ai\Chat\Factories\AbstractChatAgentFactory;
use App\Services\Ai\Chat\Tools\ClientTool;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\Configs\ReasoningMode;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\Message;
use App\Services\Ai\Chat\Values\Messages\SystemMessage;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Tools\ToolChoiceMode;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Tools\ToolNameResolver;
use Psr\Log\LoggerInterface;

/**
 * Default chat agent factory: consumes the chat IR ({@see AiRequest}) and wires a
 * {@see ChatAgent}.
 *
 * Model resolution: an explicit {@see AiRequest::$model} wins; when absent the system
 * default chat model is used ({@see WellKnownSystemModelTypes::DEFAULT}).
 *
 * IR system instructions ({@see AiRequest::$systemInstruction} plus any
 * {@see SystemMessage} entries in the history) become the agent instructions. IR content
 * parts are mapped onto the vendor message model: images and files become vendor file
 * attachments, assistant tool calls become vendor tool-call messages, tool results
 * become vendor tool-result messages. HAWKI attachment UUIDs and tool-transfer strings
 * ride the {@see AiRequest::$hawkiExtensions} and resolve through the existing storage
 * and tool infrastructure.
 */
#[Singleton()]
class ChatAgentFactory extends AbstractChatAgentFactory
{
    public function __construct(
        private readonly FileStorageService $fileStorageService,
        private readonly AiModelRepository $modelRepository,
        private readonly AiService $aiService,
        private readonly ChatToolResolver $chatToolResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createAgent(AiRequest $request): ?AgentInterface
    {
        $model = $this->resolveModel($request);
        $parameters = $this->buildParameters($request);

        $context = $this->createRequestContext(
            model: $model,
            parameters: $parameters,
            usageType: null,
            formatKey: $request->formatKey,
            usageRecordedViaListener: true,
            channel: \is_string($request->hawkiExtension(AiRequest::HAWKI_EXTENSION_CHANNEL))
                ? $request->hawkiExtension(AiRequest::HAWKI_EXTENSION_CHANNEL)
                : 'chat',
            roomId: \is_int($request->hawkiExtension(AiRequest::HAWKI_EXTENSION_ROOM_ID))
                ? $request->hawkiExtension(AiRequest::HAWKI_EXTENSION_ROOM_ID)
                : null,
        );

        $messages = $this->buildMessages($request, $context);

        $arguments = [
            'context' => $context,
            'instructions' => $this->buildInstructions($request),
            'messages' => $messages,
            'tools' => $this->buildTools($request, $context),
            'promptString' => $this->continuationPromptString($request),
        ];

        // json_schema requests carry the schema through the structured agent; the SDK
        // rejects streaming for HasStructuredOutput agents, which the formatters
        // pre-empt with a 400 on stream + json_schema.
        if (null !== $request->responseFormat && ResponseFormatType::JSON_SCHEMA === $request->responseFormat->type) {
            return new StructuredChatAgent(...$arguments, responseFormat: $request->responseFormat);
        }

        return new ChatAgent(...$arguments);
    }

    /**
     * Client-driven tool loops continue with an input whose last item is a
     * `function_call_output`: the tool results stay in the conversation history and the
     * prompt becomes a small synthetic acknowledgement turn (the vendor SDK always
     * appends the prompt as a final user message). Returns null for ordinary turns.
     */
    /**
     * Derives a provider-safe item id for replayed tool calls. The OpenAI Responses API
     * requires function_call item ids to carry the `fc_` prefix; other drivers ignore
     * the item id and link via the call id ({@see ToolCall::$resultId}) anyway.
     */
    private static function providerItemId(string $toolCallId): string
    {
        return str_starts_with($toolCallId, 'fc_') ? $toolCallId : 'fc_' . $toolCallId;
    }

    private function continuationPromptString(AiRequest $request): ?string
    {
        $lastMessage = $request->messages[array_key_last($request->messages)] ?? null;

        if (!$lastMessage instanceof ToolMessage) {
            return null;
        }

        return MessageMetaBlocks::createBlock(
            'Tool Results Delivered',
            'The tool results in the previous turn were executed by the client and are final. Continue the task based on them.',
        );
    }

    private function resolveModel(AiRequest $request): AiModel
    {
        if (null !== $request->model && '' !== $request->model) {
            return $this->modelRepository->findOneOrFail($request->model);
        }

        $systemModel = $this->aiService
            ->getSystemModels()
            ->findAllFiltered(modelType: WellKnownSystemModelTypes::DEFAULT)
            ->first();

        if (null === $systemModel?->model) {
            throw ChatAgentNotResolvedException::forMissingDefaultModel();
        }

        return $systemModel->model;
    }

    private function buildParameters(AiRequest $request): AiModelParameters
    {
        $parameters = new AiModelParameters();

        $generation = $request->generation;

        if (null !== $generation) {
            if (null !== $generation->temperature) {
                $parameters->setTemperature($generation->temperature);
            }

            if (null !== $generation->topP) {
                $parameters->setTopP($generation->topP);
            }

            if (null !== $generation->maxTokens) {
                $parameters->setMaxTokens($generation->maxTokens);
            }
        }

        $reasoning = $request->reasoning;

        if (null !== $reasoning) {
            if (ReasoningMode::DISABLED === $reasoning->mode) {
                $parameters->setMaxThinkingTokens(0);
            } elseif (null !== $reasoning->budgetTokens && 0 < $reasoning->budgetTokens) {
                $parameters->setMaxThinkingTokens($reasoning->budgetTokens);
            }
        }

        $hawkiParams = $request->hawkiExtension(AiRequest::HAWKI_EXTENSION_PARAMS);

        if (\is_array($hawkiParams)) {
            $this->applyHawkiParams($parameters, $hawkiParams);
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $hawkiParams
     */
    private function applyHawkiParams(AiModelParameters $parameters, array $hawkiParams): void
    {
        if (isset($hawkiParams['temp'])) {
            $parameters->setTemperature((float) $hawkiParams['temp']);
        }

        if (isset($hawkiParams['top_p'])) {
            $parameters->setTopP((float) $hawkiParams['top_p']);
        }

        if (isset($hawkiParams['max_tokens'])) {
            $parameters->setMaxTokens((int) $hawkiParams['max_tokens']);
        }

        if (isset($hawkiParams['max_thinking_tokens'])) {
            $parameters->setMaxThinkingTokens((int) $hawkiParams['max_thinking_tokens']);
        }
    }

    private function buildInstructions(AiRequest $request): string
    {
        $instructions = [];

        $systemText = $request->systemInstructionText();

        if (null !== $systemText) {
            $instructions[] = $systemText;
        }

        foreach ($request->messages as $message) {
            if ($message instanceof SystemMessage) {
                $instructions[] = $message->text();
            }
        }

        // json_object emulation (Chat Completions dialect only — the Open Responses
        // wire format has no such format type): the SDK's schema channel always wraps
        // an object schema, so the soft format rides the instructions instead.
        if (null !== $request->responseFormat && ResponseFormatType::JSON_OBJECT === $request->responseFormat->type) {
            $instructions[] = 'Respond with a single JSON object and no other text.';
        }

        return implode("\n\n", $instructions);
    }

    /**
     * @return array<int, \Laravel\Ai\Messages\Message>
     */
    private function buildMessages(AiRequest $request, \App\Services\Ai\Agents\Values\AgentRequestContext $context): array
    {
        $storageCategory = $request->hawkiExtension(AiRequest::HAWKI_EXTENSION_BROADCAST) === true
            ? StoredFileCategory::GROUP
            : StoredFileCategory::PRIVATE;

        /** @var array<int, Message> $messages */
        $messages = array_values(array_filter($request->messages, static fn (Message $message): bool => !$message instanceof SystemMessage));
        $lastKey = array_key_last($messages) ?? null;

        $history = new AlternatingMessageHistory();

        /** @var array<string, string> $toolNameByCallId */
        $toolNameByCallId = [];

        /** @var array{itemId: string, summary: ?string, encryptedContent: ?string}|null $pendingReasoning */
        $pendingReasoning = null;

        foreach ($messages as $key => $message) {
            $isLast = $key === $lastKey;

            if ($message instanceof UserMessage) {
                $pendingReasoning = null;
                $history->registerUserMessage(
                    $message->text() !== '' ? $message->text() : '&nbsp;',
                    $this->buildAttachments($message, $isLast, $context, $request, $storageCategory),
                );

                continue;
            }

            if ($message instanceof AssistantMessage) {
                $ownReasoning = $this->reasoningStateOf($message);
                $toolCalls = [];

                foreach ($message->toolCalls() as $part) {
                    $toolNameByCallId[$part->toolCallId] = $part->toolName;
                    $toolCalls[] = new ToolCall(
                        id: self::providerItemId($part->toolCallId),
                        name: $part->toolName,
                        arguments: $part->toolInput,
                        resultId: $part->toolCallId,
                    );
                }

                if ([] !== $toolCalls) {
                    // Replay state attaches to the tool calls: same-message reasoning
                    // (Chat Completions layout) or the reasoning-only message directly
                    // preceding this turn (Open Responses layout).
                    $reasoning = $ownReasoning ?? $pendingReasoning;
                    $pendingReasoning = null;

                    if (null !== $reasoning) {
                        $toolCalls = $this->attachReasoningState($toolCalls, $reasoning);
                    }

                    $history->registerAiToolCallMessage($message->text(), $toolCalls);

                    continue;
                }

                if (null !== $ownReasoning) {
                    // Reasoning-only assistant turn: hold for the following tool-call
                    // turn; dropped entirely when none follows (providers only require
                    // replay around tool calls).
                    $pendingReasoning = $ownReasoning;

                    continue;
                }

                $pendingReasoning = null;
                $history->registerAiMessage($message->text() !== '' ? $message->text() : '&nbsp;');

                continue;
            }

            if ($message instanceof ToolMessage) {
                $toolResults = [];

                foreach ($message->parts as $part) {
                    $toolResults[] = new ToolResult(
                        id: self::providerItemId($part->toolCallId),
                        name: $toolNameByCallId[$part->toolCallId] ?? 'unknown_tool',
                        arguments: [],
                        result: $part->result,
                        resultId: $part->toolCallId,
                    );
                }

                if ([] !== $toolResults) {
                    $history->registerToolResultMessage($toolResults);
                }
            }
        }

        return [...$history->build()];
    }

    /**
     * @return array{itemId: string, summary: ?string, encryptedContent: ?string}|null the
     *         replayable reasoning state of the message, or null when it carries none
     */
    private function reasoningStateOf(AssistantMessage $message): ?array
    {
        foreach ($message->parts as $part) {
            if (!$part instanceof ReasoningPart) {
                continue;
            }

            $itemId = $part->providerMetadata['item_id'] ?? null;

            if (null === $part->encryptedContent && !\is_string($itemId) && (null === $part->reasoning || '' === $part->reasoning)) {
                continue;
            }

            return [
                'itemId' => \is_string($itemId) && '' !== $itemId ? $itemId : 'rs_' . Str::uuid()->toString(),
                'summary' => null !== $part->reasoning && '' !== $part->reasoning ? $part->reasoning : null,
                'encryptedContent' => $part->encryptedContent,
            ];
        }

        return null;
    }

    /**
     * @param array<int, ToolCall>                                            $toolCalls
     * @param array{itemId: string, summary: ?string, encryptedContent: ?string} $reasoning
     *
     * @return array<int, ToolCall>
     */
    private function attachReasoningState(array $toolCalls, array $reasoning): array
    {
        $summary = null !== $reasoning['summary']
            ? [['type' => 'summary_text', 'text' => $reasoning['summary']]]
            : null;

        return array_map(
            static fn (ToolCall $toolCall): ToolCall => new ToolCall(
                id: $toolCall->id,
                name: $toolCall->name,
                arguments: $toolCall->arguments,
                resultId: $toolCall->resultId,
                reasoningId: $reasoning['itemId'],
                reasoningSummary: $summary,
                reasoningEncryptedContent: $reasoning['encryptedContent'],
            ),
            $toolCalls,
        );
    }

    private function buildAttachments(
        UserMessage $message,
        bool $isLast,
        \App\Services\Ai\Agents\Values\AgentRequestContext $context,
        AiRequest $request,
        StoredFileCategory $storageCategory,
    ): UserMessageAttachments {
        $attachments = new UserMessageAttachments($context);

        foreach ($this->filesFromParts($message) as $file) {
            $attachments->registerVendorFile($file);
        }

        foreach ($this->storedFilesFromParts($message, $storageCategory, $attachments) as $file) {
            $attachments->register($file);
        }

        if ($isLast && \is_array($request->hawkiExtension(AiRequest::HAWKI_EXTENSION_ATTACHMENTS))) {
            foreach ($request->hawkiExtension(AiRequest::HAWKI_EXTENSION_ATTACHMENTS) as $uuid) {
                if (!\is_string($uuid)) {
                    continue;
                }

                $file = $this->fileStorageService->retrieve(StoredFileIdentifier::fromCategoryAndUuid($storageCategory, $uuid));

                if (null === $file) {
                    $attachments->addError('One or more attachment were not found in storage.');
                    $this->logger->warning(\sprintf(
                        'Attachment with UUID "%s" not found in storage category "%s".',
                        $uuid,
                        $storageCategory->value,
                    ));

                    continue;
                }

                $attachments->register($file);
            }
        }

        return $attachments;
    }

    /**
     * Maps IR image and file parts of a user message onto vendor file attachments.
     *
     * `hawki-storage://` file parts are excluded here — they resolve to HAWKI-stored
     * files via {@see storedFilesFromParts()} instead.
     *
     * @return array<int, File>
     */
    private function filesFromParts(UserMessage $message): array
    {
        $files = [];

        foreach ($message->parts as $part) {
            if ($part instanceof ImagePart) {
                $dataUrl = null !== $part->imageUrl ? $this->parseDataUrl($part->imageUrl) : null;

                if (null !== $dataUrl) {
                    $files[] = Image::fromBase64(base64: $dataUrl['data'], mimeType: $dataUrl['mimeType']);
                } elseif (null !== $part->imageUrl) {
                    $files[] = Image::fromUrl($part->imageUrl);
                } elseif (null !== $part->imageData) {
                    $files[] = Image::fromBase64(base64: $part->imageData->data, mimeType: $part->imageData->mediaType);
                }

                continue;
            }

            if ($part instanceof FilePart) {
                if (null !== $part->fileUrl && str_starts_with($part->fileUrl, AiRequest::HAWKI_STORAGE_SCHEME)) {
                    continue;
                }

                $dataUrl = null !== $part->fileUrl ? $this->parseDataUrl($part->fileUrl) : null;

                if (null !== $dataUrl) {
                    $files[] = Document::fromBase64(base64: $dataUrl['data'], mimeType: $dataUrl['mimeType']);
                } elseif (null !== $part->fileUrl) {
                    $files[] = Document::fromUrl($part->fileUrl);
                } elseif (null !== $part->fileData) {
                    $files[] = Document::fromBase64(base64: $part->fileData->data, mimeType: $part->fileData->mediaType);
                }
            }
        }

        return $files;
    }

    /**
     * Resolves `hawki-storage://<uuid>` file parts of a user message onto HAWKI-stored
     * files in the request's storage category (private vs. group). Missing files are
     * reported as attachment errors rather than aborting the request — the same
     * semantics as the {@see AiRequest::HAWKI_EXTENSION_ATTACHMENTS} path.
     *
     * @return array<int, \App\Services\Storage\Interfaces\FileInterface>
     */
    private function storedFilesFromParts(UserMessage $message, StoredFileCategory $storageCategory, UserMessageAttachments $attachments): array
    {
        $files = [];

        foreach ($message->parts as $part) {
            if (!$part instanceof FilePart
                || null === $part->fileUrl
                || !str_starts_with($part->fileUrl, AiRequest::HAWKI_STORAGE_SCHEME)) {
                continue;
            }

            $uuid = substr($part->fileUrl, \strlen(AiRequest::HAWKI_STORAGE_SCHEME));
            $file = $this->fileStorageService->retrieve(StoredFileIdentifier::fromCategoryAndUuid($storageCategory, $uuid));

            if (null === $file) {
                $attachments->addError('One or more attachment were not found in storage.');
                $this->logger->warning(\sprintf(
                    'Attachment with UUID "%s" not found in storage category "%s".',
                    $uuid,
                    $storageCategory->value,
                ));

                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @return null|array{mimeType: string, data: string}
     */
    private function parseDataUrl(string $url): ?array
    {
        if (!str_starts_with($url, 'data:')) {
            return null;
        }

        if (!preg_match('#^data:([^;,]+)(;base64)?,(.*)$#s', $url, $matches)) {
            return null;
        }

        return ['mimeType' => $matches[1], 'data' => $matches[3]];
    }

    private function buildTools(AiRequest $request, \App\Services\Ai\Agents\Values\AgentRequestContext $context): iterable
    {
        if (ToolChoiceMode::NONE === $request->toolChoice?->mode) {
            return [];
        }

        $tools = [];
        $serverToolNames = [];

        $transferStrings = $request->hawkiExtension(AiRequest::HAWKI_EXTENSION_TOOLS);

        if (\is_array($transferStrings)) {
            foreach ($this->chatToolResolver->findTools($transferStrings, $context) as $tool) {
                $tools[] = $tool;
                $serverToolNames[] = ToolNameResolver::resolve($tool);
            }
        }

        foreach ($request->tools ?? [] as $definition) {
            if (\in_array($definition->name, $serverToolNames, true)) {
                continue;
            }

            // Intercept: a server-side HAWKI tool with this name executes in the HAWKI runtime.
            if ($this->hasServerTool($definition->name, $context)) {
                $serverTool = $this->getToolResolver()->resolveToolByName($definition->name, $context);
                $tools[] = $serverTool;
                $serverToolNames[] = $definition->name;

                continue;
            }

            // Hand off: everything the server cannot map is passed through to the client.
            $tools[] = new ClientTool($definition, $this->logger);
        }

        return $tools;
    }

    /**
     * Probes the server-side tool registry of the resolved model without throwing.
     */
    private function hasServerTool(string $toolName, \App\Services\Ai\Agents\Values\AgentRequestContext $context): bool
    {
        return $context->model->tools
            ->first(static fn (\App\Models\Ai\AiTool $tool): bool => $tool->name === $toolName) !== null;
    }
}
