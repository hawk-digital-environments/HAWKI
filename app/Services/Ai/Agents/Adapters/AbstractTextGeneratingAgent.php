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

/**
 * Ready-to-use base class for HAWKI chat-style agents that generate text responses.
 *
 * Extends {@see AbstractLaravelAgent} and implements the full set of Laravel AI
 * contracts required for a conversational, tool-capable agent:
 * - {@see Conversational} — supplies prior turn messages to the model.
 * - {@see HasTools} — exposes optional tool bindings (e.g. MCP tools).
 * - {@see HasProviderOptions} — forwards provider-specific driver options from the
 *   {@see AgentRequestContext} to the underlying gateway.
 * - {@see HasMiddleware} — registers {@see LoggingMiddleware} for every request.
 *
 * Construction validates that either a `$promptString` or a non-empty `$messages` array is
 * provided. When `$messages` is used the last entry must be a {@see UserMessage} with
 * non-empty content; that message is popped from the history and used as the prompt,
 * so the remaining messages are passed as conversation history via {@see messages()}.
 *
 * Sampling parameters (`maxTokens`, `temperature`, `topP`) are only forwarded to the provider
 * when the resolved model declares support for sampling parameters via its flags; otherwise
 * `null` is returned so the provider can apply its own defaults.
 *
 * Example (typical factory usage):
 * ```php
 * return new ChatAgent(
 *     context: $this->createRequestContext($model, $parameters),
 *     instructions: $systemPrompt,
 *     messages: $history->build(),        // last element must be a UserMessage
 *     tools: $toolResolver->findTools($toolNames, $context),
 * );
 * ```
 */
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
            // Carry attachments from the popped user message only when the caller did not supply them explicitly.
            if (empty($this->attachments) && $lastMessage instanceof UserMessage) {
                $this->attachments = $lastMessage->attachments->all();
            }
        }
    }

    /**
     * Returns the system instructions wrapped in the HKI_META preamble so the model
     * understands how to handle metadata blocks embedded in user messages.
     *
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

    /**
     * Returns the maximum output token limit, or null when the model does not support
     * sampling parameters (letting the provider apply its own default).
     */
    public function maxTokens(): int|null
    {
        if ($this->context->model->flags->hasFeatureSamplingParameters()) {
            return $this->context->modelParameters->getMaxTokens();
        }
        return null;
    }

    /**
     * Returns the sampling temperature, or null when the model does not support
     * sampling parameters.
     */
    public function temperature(): float|null
    {
        if ($this->context->model->flags->hasFeatureSamplingParameters()) {
            return $this->context->modelParameters->getTemperature();
        }
        return null;
    }

    /**
     * Returns the top-p (nucleus sampling) value, or null when the model does not support
     * sampling parameters.
     */
    public function topP(): float|null
    {
        if ($this->context->model->flags->hasFeatureSamplingParameters()) {
            return $this->context->modelParameters->getTopP();
        }
        return null;
    }

    /**
     * Returns the prior conversation turns (all messages except the current user prompt).
     *
     * @inheritDoc
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Returns the tools available to this agent, or an empty iterable when none were supplied.
     *
     * @inheritDoc
     */
    public function tools(): iterable
    {
        return $this->tools ?? [];
    }

    /**
     * Registers {@see LoggingMiddleware} so every outbound request is logged with model,
     * provider, agent class, and the authenticated user's ID.
     *
     * @inheritDoc
     */
    public function middleware(): array
    {
        return [
            new LoggingMiddleware()
        ];
    }

    /**
     * Delegates to the provider adapter so driver-specific options (e.g. extended thinking,
     * custom headers) can be injected without coupling the agent to a particular gateway.
     *
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
