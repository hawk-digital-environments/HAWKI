<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Adapters;


use App\Services\Ai\Agents\Exceptions\InvalidAgentConfigurationException;
use App\Services\Ai\Agents\Middleware\LoggingMiddleware;
use App\Services\Ai\Agents\Utils\MessageMetaBlocks;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\UserMessage;
use Stringable;

abstract class AbstractTextGeneratingAgent extends AbstractLaravelAgent implements Conversational, HasTools, HasProviderOptions, HasMiddleware
{
    public function __construct(
        protected AgentRequestContext $context,
        protected string              $instructions,
        protected array               $messages = [],
        protected iterable|null       $tools = null,
        protected string|null         $promptString = null,
        protected array|null          $attachments = null,
    )
    {
        if (empty($this->promptString)) {
            if (empty($this->messages)) {
                throw InvalidAgentConfigurationException::forMissingPromptOrMessages();
            }

            $lastMessage = array_pop($this->messages);
            if (!$lastMessage instanceof Message) {
                throw InvalidAgentConfigurationException::forLastMessageNotAMessageInstance();
            }
            if ($lastMessage->role !== MessageRole::User) {
                throw InvalidAgentConfigurationException::forLastMessageNotUserRole();
            }
            if (empty($lastMessage->content)) {
                throw InvalidAgentConfigurationException::forLastMessageEmptyContent();
            }
            $this->promptString = $lastMessage->content;
            if (empty($this->attachments) && $lastMessage instanceof UserMessage) {
                $this->attachments = $lastMessage->attachments->all();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function instructions(): Stringable|string
    {
        // @todo we should probably make this a registry, to provide system wide instructions for all agents.
        return MessageMetaBlocks::wrapInstructions($this->instructions);
    }

    public function getContext(): AgentRequestContext
    {
        return $this->context;
    }

    protected function getPromptString(): string
    {
        return $this->promptString;
    }

    protected function getAttachments(): array
    {
        return $this->attachments ?? [];
    }

    public function maxTokens(): int|null
    {
        if ($this->context->model->flags->hasFeatureSamplingParameters()) {
            return $this->context->modelParameters->getMaxTokens();
        }
        return null;
    }

    public function temperature(): float|null
    {
        if ($this->context->model->flags->hasFeatureSamplingParameters()) {
            return $this->context->modelParameters->getTemperature();
        }
        return null;
    }

    public function topP(): float|null
    {
        if ($this->context->model->flags->hasFeatureSamplingParameters()) {
            return $this->context->modelParameters->getTopP();
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    public function tools(): iterable
    {
        return $this->tools ?? [];
    }

    /**
     * @inheritDoc
     */
    public function middleware(): array
    {
        return [
            new LoggingMiddleware()
        ];
    }

    /**
     * @inheritDoc
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $this->context->provider->adapter->getAdditionalDriverOptions(
            $this,
            $this->context
        );
    }
}
