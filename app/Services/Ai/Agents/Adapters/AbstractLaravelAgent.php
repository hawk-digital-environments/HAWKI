<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Adapters;


use App\Services\Ai\Agents\Contracts\AgentInterface as HawkiAgentInterface;
use App\Services\Ai\Agents\Events\AgentResponseReceivedEvent;
use App\Services\Ai\Agents\Events\AgentSendingEvent;
use App\Services\Ai\Agents\Events\AgentStreamCompletedEvent;
use App\Services\Ai\Agents\Events\AgentStreamInitiatedEvent;
use App\Services\Ai\Agents\Exceptions\AgentStateException;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Services\Ai\Tools\Exceptions\ToolAccessException;
use App\Services\Ai\Tools\LaravelAi\AuthorizedTextGateway;
use App\Services\Ai\Tools\LaravelAi\ToolExecutionState;
use App\Services\Ai\Values\TokenUsage;
use Illuminate\Broadcasting\Channel;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent as LaravelAgentInterface;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\QueuedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * Base adapter that bridges the Laravel AI package's {@see LaravelAgentInterface} with HAWKI's own
 * {@see HawkiAgentInterface}, handling the lifecycle events and token-usage bookkeeping that every
 * concrete agent needs.
 *
 * Concrete agents extend this class (typically via {@see AbstractTextGeneratingAgent}) and provide
 * the prompt string and optional file attachments through protected hook methods.
 *
 * The class fires four domain events around each request so that listeners can react without
 * coupling to the agent implementation:
 * - {@see AgentSendingEvent} — before any HTTP call is made (both send and stream).
 * - {@see AgentResponseReceivedEvent} — after a synchronous response is received.
 * - {@see AgentStreamInitiatedEvent} — after the streaming response object is created but before
 *   data starts flowing.
 * - {@see AgentStreamCompletedEvent} — when the stream closes and final token usage is known.
 *
 * Token usage ({@see getUsage()}) is only available after {@see send()} or after the stream
 * returned by {@see sendStreaming()} has been fully consumed. Calling it before that throws an
 * {@see AgentStateException}.
 */
abstract class AbstractLaravelAgent implements LaravelAgentInterface, HawkiAgentInterface
{
    use Promptable {
        prompt as private promptThroughSdk;
        stream as private streamThroughSdk;
    }

    // HAWKI's context and provider-step guards are supplied by send()/sendStreaming().
    // Reject alternate SDK entry points rather than accepting a caller-selected driver/model.
    final public function prompt(Decisions|string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
    {
        throw ToolAccessException::denied();
    }

    final public function stream(Decisions|string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): StreamableAgentResponse
    {
        throw ToolAccessException::denied();
    }

    final public function queue(Decisions|string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): QueuedAgentResponse
    {
        throw ToolAccessException::denied();
    }

    final public function broadcast(Decisions|string $prompt, Channel|array $channels, array $attachments = [], bool $now = false, Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
    {
        throw ToolAccessException::denied();
    }

    final public function broadcastNow(Decisions|string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
    {
        throw ToolAccessException::denied();
    }

    final public function broadcastOnQueue(Decisions|string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): QueuedAgentResponse
    {
        throw ToolAccessException::denied();
    }

    /** Queue producers must persist trusted input and actor IDs, then rebuild through a HAWKI factory. */
    final public function __serialize(): array
    {
        throw ToolAccessException::denied();
    }

    /** Reject legacy serialized SDK jobs before their generic broadcast error handler can run. */
    final public function __unserialize(array $values): void
    {
        throw ToolAccessException::denied();
    }

    private Usage|null $usage = null;

    /**
     * Returns the user-turn prompt text to send to the model.
     * Called by both {@see send()} and {@see sendStreaming()}.
     */
    abstract protected function getPromptString(): string;

    /**
     * Returns the file attachments to include with the prompt.
     * Defaults to an empty array; override to attach files to the request.
     */
    protected function getAttachments(): array
    {
        return [];
    }

    /**
     * Returns the token usage from the last completed request.
     *
     * @throws AgentStateException when called before {@see send()} or before the stream from
     *                             {@see sendStreaming()} has been fully consumed.
     */
    public function getUsage(): TokenUsage
    {
        if (!$this->usage) {
            throw AgentStateException::forUsageNotAvailable();
        }

        return TokenUsage::fromLaravelUsage($this->usage, $this->getContext()->model);
    }

    /**
     * Sends the prompt to the AI provider and returns the complete response synchronously.
     *
     * Dispatches {@see AgentSendingEvent} before and {@see AgentResponseReceivedEvent} after
     * the provider call. Token usage is stored and becomes accessible via {@see getUsage()}.
     */
    public function send(): AgentResponse
    {
        $this->installAuthorizedGateway();
        AgentSendingEvent::dispatch($this, $this->getContext(), $this->getContext()->provider);

        $response = $this->promptThroughSdk(
            prompt: $this->getPromptString(),
            attachments: $this->getAttachments(),
            provider: (string)ProviderDriverPortal::fromProviderProxy($this->getContext()->provider),
            model: $this->getContext()->model->model_id
        );

        app(ToolExecutionState::class)->check($this->getContext());
        $this->usage = $response->usage;

        AgentResponseReceivedEvent::dispatch($this, $this->getContext(), $this->getContext()->provider, $response, $response->usage);

        return $response;
    }

    /**
     * Sends the prompt to the AI provider and returns a streamable response.
     *
     * Dispatches {@see AgentSendingEvent} before the HTTP call and
     * {@see AgentStreamInitiatedEvent} immediately after the stream object is created.
     * {@see AgentStreamCompletedEvent} is dispatched once the stream closes and token usage
     * is available.
     */
    public function sendStreaming(): StreamableAgentResponse
    {
        $this->installAuthorizedGateway();
        AgentSendingEvent::dispatch($this, $this->getContext(), $this->getContext()->provider);

        $response = $this->streamThroughSdk(
            prompt: $this->getPromptString(),
            attachments: $this->getAttachments(),
            provider: (string)ProviderDriverPortal::fromProviderProxy($this->getContext()->provider),
            model: $this->getContext()->model->model_id
        );

        $response->then(function (AgentResponse $response) {
            app(ToolExecutionState::class)->check($this->getContext());
            $this->usage = $response->usage;

            AgentStreamCompletedEvent::dispatch($this, $this->getContext(), $this->getContext()->provider, $response, $response->usage);
        });

        AgentStreamInitiatedEvent::dispatch($this, $this->getContext(), $this->getContext()->provider, $response);

        return $response;
    }

    /**
     * Wraps the provider driver so every SDK loop step re-checks tool authorization.
     *
     * This is a security guard, so a driver that cannot be wrapped must not be used at all:
     * every in-tree driver uses the SDK's HasTextGateway trait, and a driver that does not is a
     * programming error rather than a runtime condition callers could recover from.
     */
    private function installAuthorizedGateway(): void
    {
        $driver = $this->getContext()->provider->driver;
        // useTextGateway() is part of the TextProvider contract; textGateway() only comes with HasTextGateway.
        if (!$driver instanceof TextProvider || !method_exists($driver, 'textGateway')) {
            throw new \LogicException(sprintf(
                'Provider driver %s cannot be wrapped in %s; text generation is refused because tool authorization could not be enforced.',
                get_debug_type($driver),
                AuthorizedTextGateway::class
            ));
        }
        if (!$driver->textGateway() instanceof AuthorizedTextGateway) {
            $driver->useTextGateway(new AuthorizedTextGateway($driver->textGateway()));
        }
    }
}
