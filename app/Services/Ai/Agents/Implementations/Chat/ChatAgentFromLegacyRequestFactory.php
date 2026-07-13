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
use Laravel\Ai\Messages\MessageRole;
use Psr\Log\LoggerInterface;

/**
 * Factory that creates a {@see ChatAgent} from the legacy frontend request payload format
 * ({@see LegacyChatRequestPayload} documents the shape).
 *
 * {@see createAgent()} returns `null` for any request that does not match this shape, allowing
 * higher-priority factories registered in {@see AgentRegistry} to claim the request first.
 *
 * Consecutive messages with the same role are merged by {@see AlternatingMessageHistory} before
 * being passed to the agent. File attachments referenced by UUID are fetched from
 * {@see FileStorageService}; missing files are logged and reported to the model as a metadata
 * error block rather than aborting the request.
 */
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

    /**
     * Returns a {@see ChatAgent} when the request matches the legacy payload shape, null otherwise.
     */
    public function createAgent(mixed $request): AgentInterface|null
    {
        $payload = LegacyChatRequestPayload::tryFromRequest($request);
        if ($payload === null) {
            return null;
        }

        $model = $this->getModelFromPayload($payload);
        $context = $this->createRequestContext(
            $model,
            $this->getModelParametersFromPayload($payload)
        );

        return new ChatAgent(
            context: $context,
            instructions: $payload->systemInstructions(),
            messages: $this->getMessagesFromPayload($payload, $context),
            tools: $this->toolResolver->findTools($payload->tools(), $context)
        );
    }

    private function getModelFromPayload(LegacyChatRequestPayload $payload): AiModel
    {
        $modelId = $payload->modelId();
        if ($modelId === '') {
            throw new ModelNotInPayloadException($payload->toArray());
        }

        return $this->modelRepository->findOneOrFail($modelId);
    }

    /**
     * Maps the legacy `params` keys (`temp`, `top_p`, `max_tokens`, `max_thinking_tokens`) to
     * an {@see AiModelParameters} instance. Only keys that are present in the payload are set;
     * absent keys fall back to the model's stored defaults downstream.
     */
    private function getModelParametersFromPayload(LegacyChatRequestPayload $payload): AiModelParameters
    {
        $params = $payload->params();

        $modelParameters = new AiModelParameters();

        if (isset($params['temp'])) {
            $modelParameters->setTemperature((float)$params['temp']);
        }
        if (isset($params['top_p'])) {
            $modelParameters->setTopP((float)$params['top_p']);
        }
        if (isset($params['max_tokens'])) {
            $modelParameters->setMaxTokens((int)$params['max_tokens']);
        }
        if (isset($params['max_thinking_tokens'])) {
            $modelParameters->setMaxThinkingTokens((int)$params['max_thinking_tokens']);
        }

        return $modelParameters;
    }

    /**
     * Converts the payload messages array into a Laravel AI message array suitable for passing
     * to the agent constructor.
     *
     * System messages are skipped (handled separately via {@see LegacyChatRequestPayload::systemInstructions()}).
     * Attachment UUIDs are resolved to stored files; missing files are collected as errors on
     * the {@see UserMessageAttachments} instance rather than aborting processing. The resulting
     * message list is fed through {@see AlternatingMessageHistory} to guarantee alternating roles.
     *
     * The `broadcast` flag controls which storage category (group vs. private) is used when
     * resolving attachment UUIDs.
     */
    private function getMessagesFromPayload(LegacyChatRequestPayload $payload, AgentRequestContext $context): array
    {
        $storageCategory = $payload->isBroadcast() ? StoredFileCategory::GROUP : StoredFileCategory::PRIVATE;

        $history = new AlternatingMessageHistory();
        foreach ($payload->messages() as $payloadMessage) {
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
}
