<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Chat;


use App\Models\Ai\AiModel;
use App\Services\Ai\Agent\Chat\Values\ChatRequest;
use App\Services\Ai\Agent\Contracts\AgentRequestFactoryInterface;
use App\Services\Ai\Exceptions\ModelNotInPayloadException;
use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use App\Services\Ai\Repositories\AiModelRepository;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\UsageTypes\UsageContext;
use Illuminate\Container\Attributes\Singleton;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\Message;

#[Singleton]
readonly class ChatRequestFactory implements AgentRequestFactoryInterface
{
    public function __construct(
        private UsageContext              $usageContext,
        private AiModelCapabilityRegistry $capabilityRegistry,
        private FileStorageService        $fileStorageService,
        private AiModelRepository         $modelRepository
    )
    {
    }

    public function createFromPayload(array $payload): ChatRequest
    {
        $model = $this->getModelFromPayload($payload);
        $isStreaming = (bool)($payload['stream'] ?? false);

        return new ChatRequest(
            model: $model,
            parameters: $this->getModelParametersFromPayload($payload),
            usageType: $this->usageContext->get(),
            instructions: $this->getInstructionsFromPayload($payload),
            capabilities: $this->getRequestedCapabilitiesFromPayload($payload, $model),
            tools: $this->getRequestedToolsFromPayload($payload, $this->getRequestedCapabilitiesFromPayload($payload, $model), $model),
            messages: $this->getMessagesFromPayload($payload, $model),
            streaming: $isStreaming
        );
    }

    private function getModelFromPayload(array $payload): AiModel
    {
        $modelId = $payload['model'] ?? null;
        if (empty($modelId)) {
            throw new ModelNotInPayloadException($payload);
        }

        return $this->modelRepository->findOneOrFail($modelId);
    }

    private function getModelParametersFromPayload(array $payload): ModelParameters
    {
        $params = new ModelParameters();

        if (isset($payload['params']['temp'])) {
            $params->setTemperature((float)$payload['params']['temp']);
        }
        if (isset($payload['params']['top_p'])) {
            $params->setTopP((float)$payload['params']['top_p']);
        }
        if (isset($payload['params']['max_tokens'])) {
            $params->setMaxTokens((int)$payload['params']['max_tokens']);
        }
        if (isset($payload['params']['max_thinking_tokens'])) {
            $params->setMaxThinkingTokens((int)$payload['params']['max_thinking_tokens']);
        }

        return $params;
    }

    private function getInstructionsFromPayload(array $payload): string
    {
        foreach ($payload['messages'] ?? [] as $message) {
            if (isset($message['role']) && $message['role'] === 'system' && isset($message['content'])) {
                return $message['content']['text'];
            }
        }

        throw new \RuntimeException('No system instructions found in messages payload.');
    }

    private function getRequestedCapabilitiesFromPayload(array $payload, AiModel $model): array
    {
        // Currently capabilities coming in as "tools" from the frontend, we need to parse them out.
        $tools = $payload['tools'] ?? [];
        $capabilityStrings = [];

        foreach ($tools as $tool) {
            if ($this->capabilityRegistry->has($tool)) {
                if (!$model->capabilities->canUse($tool)) {
                    throw new \InvalidArgumentException(sprintf('Capability "%s" is not available for model "%s".', $tool, $model->model_id));
                }
                $capabilityStrings[] = $tool;
            }
        }

        return $capabilityStrings;
    }

    private function getRequestedToolsFromPayload(array $payload, array $capabilityStrings, AiModel $model): array
    {
        $tools = $payload['tools'] ?? [];
        $validatedTools = [];
        $modelTools = $model->tools;
        foreach ($tools as $toolName) {
            if (!$modelTools->hasWithName($toolName)) {
                if (in_array($toolName, $capabilityStrings, true)) {
                    continue; // This is a capability, not a tool, so we skip the tool validation for it.
                }
                throw new \InvalidArgumentException(sprintf('Tool "%s" is not available for model "%s".', $toolName, $model->label));
            }
            $validatedTools[] = $toolName;
        }
        return $validatedTools;
    }

    private function getMessagesFromPayload(array $payload, AiModel $model): array
    {
        $storageCategory = ($payload['broadcast'] ?? null) === true ? StoredFileCategory::GROUP : StoredFileCategory::PRIVATE;

        $messages = [];
        $nextExpectedRole = MessageRole::USER;
        foreach ($payload['messages'] ?? [] as $payloadMessage) {
            if (($payloadMessage['role'] ?? null) === 'system') {
                continue; // Skip system instructions as they are handled separately
            }

            if (!isset($payloadMessage['role'], $payloadMessage['content']['text'])) {
                // @todo exception
                throw new \InvalidArgumentException('Each message must have a "role" and "content.text" field.');
            }

            $payloadRole = MessageRole::tryFrom($payloadMessage['role']);
            if (!in_array($payloadRole, [MessageRole::USER, MessageRole::ASSISTANT], true)) {
                // @todo exception
                throw new \InvalidArgumentException(sprintf(
                    'Invalid message role "%s". Allowed roles are "%s" and "%s".',
                    $payloadRole->value ?? $payloadMessage['role'],
                    MessageRole::USER->value,
                    MessageRole::ASSISTANT->value
                ));
            }

            // Enforce alternating roles (user -> assistant -> user -> assistant, etc.)
            if ($payloadRole !== $nextExpectedRole) {
                $messages[] = new Message(
                    role: $nextExpectedRole,
                    content: ''
                );
            }

            $message = new Message(
                role: $payloadRole,
                content: $payloadMessage['content']['text']
            );

            if ($model->settings->canHandleFiles() && !empty($payloadMessage['content']['attachments']) && is_array($payloadMessage['content']['attachments'])) {
                $this->addAttachmentsToMessage($message, $payloadMessage['content']['attachments'], $storageCategory);
            }

            $nextExpectedRole = $payloadRole === MessageRole::USER ? MessageRole::ASSISTANT : MessageRole::USER;
            $messages[] = $message;
        }

        return $messages;
    }

    private function addAttachmentsToMessage(
        Message            $message,
        array              $attachmentUuids,
        StoredFileCategory $category): void
    {
        foreach ($attachmentUuids as $uuid) {
            $file = $this->fileStorageService->retrieve(StoredFileIdentifier::fromCategoryAndUuid($category, $uuid));
            $extracts = ($file->getExtracts()?->count() ?? 0) > 0 ? $file->getExtracts() : [$file];
            foreach ($extracts as $extract) {
                if ($extract->getFileType() === FileType::IMAGE) {
                    $message->addContent(new ImageContent(
                        content: base64_encode($extract->getContent()),
                        sourceType: SourceType::BASE64,
                        mediaType: $extract->getMimeType(),
                    ));
                } else if ($extract->getFileType() === FileType::PLAIN_TEXT) {
                    $message->addContent(new FileContent(
                        content: base64_encode($extract->getContent()),
                        sourceType: SourceType::BASE64,
                        mediaType: $extract->getMimeType(),
                    ));
                }
            }
        }
    }
}
