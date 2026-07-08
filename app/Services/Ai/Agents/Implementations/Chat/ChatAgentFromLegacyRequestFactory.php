<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations\Chat;


use App\Models\Ai\AiModel;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Exceptions\InvalidLegacyRequestPayloadException;
use App\Services\Ai\Agents\Implementations\AbstractAgentFactory;
use App\Services\Ai\Agents\Utils\AlternatingMessageHistory;
use App\Services\Ai\Agents\Utils\UserMessageAttachments;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Exceptions\ModelNotInPayloadException;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\UserMessage;
use Psr\Log\LoggerInterface;

#[Singleton]
class ChatAgentFromLegacyRequestFactory extends AbstractAgentFactory
{
    public function __construct(
        private readonly FileStorageService $fileStorageService,
        private readonly AiModelRepository  $modelRepository,
        private readonly ChatToolResolver   $toolResolver,
        private readonly LoggerInterface    $logger
    )
    {
    }

    public function createAgent(mixed $request): AgentInterface|null
    {
        if (
            !is_array($request)
            || !is_array($request['payload'] ?? null)
            || !is_array($request['payload']['messages'] ?? null)
            || !is_string($request['payload']['model'] ?? null)
        ) {
            return null;
        }

        $payload = $request['payload'];
        $model = $this->getModelFromPayload($payload);
        $context = $this->createRequestContext(
            $model,
            $this->getModelParametersFromPayload($payload)
        );

        $instructions = $this->getInstructionsFromPayload($payload);
        $messages = $this->getMessagesFromPayload($payload, $context);

        logFile($messages);

        return new ChatAgent(
            context: $context,
            instructions: $instructions,
            messages: $messages,
            tools: $this->toolResolver->findTools($payload['tools'] ?? [], $context)
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

    private function getModelParametersFromPayload(array $payload): AiModelParameters
    {
        $params = new AiModelParameters();

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

        throw InvalidLegacyRequestPayloadException::forMissingSystemInstructions();
    }

    private function getMessagesFromPayload(array $payload, AgentRequestContext $context): array
    {
        $storageCategory = ($payload['broadcast'] ?? null) === true ? StoredFileCategory::GROUP : StoredFileCategory::PRIVATE;

        $history = new AlternatingMessageHistory();
        foreach ($payload['messages'] ?? [] as $payloadMessage) {
            if (($payloadMessage['role'] ?? null) === 'system') {
                continue; // Skip system instructions as they are handled separately
            }

            if (!isset($payloadMessage['role'], $payloadMessage['content']['text'])) {
                throw InvalidLegacyRequestPayloadException::forMessageMissingFields();
            }

            $payloadRole = MessageRole::tryFrom($payloadMessage['role']);
            if (!in_array($payloadRole, [MessageRole::User, MessageRole::Assistant], true)) {
                throw InvalidLegacyRequestPayloadException::forInvalidMessageRole($payloadRole->value ?? $payloadMessage['role']);
            }

            if ($payloadRole === MessageRole::User) {
                $attachments = new UserMessageAttachments($context);
                if (!empty($payloadMessage['content']['attachments']) && is_array($payloadMessage['content']['attachments'])) {
                    foreach ($payloadMessage['content']['attachments'] as $uuid) {
                        $file = $this->fileStorageService->retrieve(StoredFileIdentifier::fromCategoryAndUuid($storageCategory, $uuid));
                        if ($file === null) {
                            $attachments->addError('One or more attachment were not found in storage.');
                            $this->logger->warning(sprintf('Attachment with UUID "%s" not found in storage category "%s".', $uuid, $storageCategory->value));
                            continue;
                        }
                        $attachments->register($file);
                    }
                }

                $history->registerUserMessage($payloadMessage['content']['text'], $attachments);
                continue;
            }

            $history->registerAiMessage($payloadMessage['content']['text']);
        }

        return [...$history->build()];
    }

    private function createMessage(MessageRole $role, string $content): Message
    {
        if (empty(trim($content))) {
            return new Message(
                role: $role,
                content: '[message without content]'
            );
        }
        if ($role === MessageRole::User) {
            return new UserMessage(
                content: $content
            );
        }
        return new Message(
            role: $role,
            content: $content
        );
    }
}
