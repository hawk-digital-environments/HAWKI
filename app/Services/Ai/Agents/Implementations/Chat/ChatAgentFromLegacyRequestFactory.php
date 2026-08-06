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
 * Factory that creates a {@see ChatAgent} from the legacy frontend request payload format.
 *
 * The legacy format is a plain array with the shape:
 * ```php
 * [
 *     'payload' => [
 *         'model'     => 'gpt-4o',           // required: model slug
 *         'messages'  => [                    // required
 *             ['role' => 'system',    'content' => ['text' => '...']],  // system instructions
 *             ['role' => 'user',      'content' => ['text' => '...', 'attachments' => ['uuid1']]],
 *             ['role' => 'assistant', 'content' => ['text' => '...']],
 *             // ... more turns ...
 *         ],
 *         'params'    => ['temp' => 0.7, 'top_p' => 1.0, 'max_tokens' => 2048],  // optional
 *         'tools'     => ['capability:web_search:auto'],                           // optional
 *         'broadcast' => false,               // optional: true → group storage for attachments
 *     ],
 * ]
 * ```
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

    /**
     * Maps the legacy `params` keys (`temp`, `top_p`, `max_tokens`, `max_thinking_tokens`) to
     * an {@see AiModelParameters} instance. Only keys that are present in the payload are set;
     * absent keys fall back to the model's stored defaults downstream.
     */
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

    /**
     * Extracts the system instructions from the first message whose role is "system".
     *
     * @throws InvalidLegacyRequestPayloadException when no system message is found.
     */
    private function getInstructionsFromPayload(array $payload): string
    {
        foreach ($payload['messages'] ?? [] as $message) {
            if (isset($message['role']) && $message['role'] === 'system' && isset($message['content'])) {
                return $message['content']['text'];
            }
        }

        throw InvalidLegacyRequestPayloadException::forMissingSystemInstructions();
    }

    /**
     * Converts the payload messages array into a Laravel AI message array suitable for passing
     * to the agent constructor.
     *
     * System messages are skipped (handled separately via {@see getInstructionsFromPayload()}).
     * Attachment UUIDs are resolved to stored files; missing files are collected as errors on
     * the {@see UserMessageAttachments} instance rather than aborting processing. The resulting
     * message list is fed through {@see AlternatingMessageHistory} to guarantee alternating roles.
     *
     * The `broadcast` flag controls which storage category (group vs. private) is used when
     * resolving attachment UUIDs.
     */
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
}
