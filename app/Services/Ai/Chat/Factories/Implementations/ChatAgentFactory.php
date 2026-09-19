<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Factories\Implementations;

use App\Models\Ai\AiModel;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Implementations\Chat\ChatToolResolver;
use App\Services\Ai\Agents\Utils\AlternatingMessageHistory;
use App\Services\Ai\Agents\Utils\UserMessageAttachments;
use App\Services\Ai\AiService;
use App\Services\Ai\Chat\Exceptions\ChatAgentNotResolvedException;
use App\Services\Ai\Chat\Factories\AbstractChatAgentFactory;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\Configs\ReasoningMode;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\Message;
use App\Services\Ai\Chat\Values\Messages\SystemMessage;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Tools\ToolChoiceMode;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
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
        );

        $messages = $this->buildMessages($request, $context);

        return new ChatAgent(
            context: $context,
            instructions: $this->buildInstructions($request),
            messages: $messages,
            tools: $this->buildTools($request, $context),
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

        foreach ($messages as $key => $message) {
            $isLast = $key === $lastKey;

            if ($message instanceof UserMessage) {
                $history->registerUserMessage(
                    $message->text() !== '' ? $message->text() : '&nbsp;',
                    $this->buildAttachments($message, $isLast, $context, $request, $storageCategory),
                );

                continue;
            }

            if ($message instanceof AssistantMessage) {
                $toolCalls = [];

                foreach ($message->toolCalls() as $part) {
                    $toolNameByCallId[$part->toolCallId] = $part->toolName;
                    $toolCalls[] = new ToolCall(
                        id: $part->toolCallId,
                        name: $part->toolName,
                        arguments: $part->toolInput,
                    );
                }

                if ([] !== $toolCalls) {
                    $history->registerAiToolCallMessage($message->text(), $toolCalls);

                    continue;
                }

                $history->registerAiMessage($message->text() !== '' ? $message->text() : '&nbsp;');

                continue;
            }

            if ($message instanceof ToolMessage) {
                $toolResults = [];

                foreach ($message->parts as $part) {
                    $toolResults[] = new ToolResult(
                        id: $part->toolCallId,
                        name: $toolNameByCallId[$part->toolCallId] ?? 'unknown_tool',
                        arguments: [],
                        result: $part->result,
                    );
                }

                if ([] !== $toolResults) {
                    $history->registerToolResultMessage($toolResults);
                }
            }
        }

        return [...$history->build()];
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

        $transferStrings = $request->hawkiExtension(AiRequest::HAWKI_EXTENSION_TOOLS);

        if (\is_array($transferStrings)) {
            foreach ($this->chatToolResolver->findTools($transferStrings, $context) as $tool) {
                $tools[] = $tool;
            }
        }

        foreach ($request->tools ?? [] as $definition) {
            $settings = $definition->metadata['settings'] ?? [];
            $tools[] = $this->getToolResolver()->resolveToolByName(
                $definition->name,
                $context,
                \is_array($settings) ? $settings : [],
            );
        }

        return $tools;
    }
}
