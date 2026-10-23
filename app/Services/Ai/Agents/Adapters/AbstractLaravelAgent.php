<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Adapters;


use App\Services\Ai\Agents\Contracts\AgentInterface as HawkiAgentInterface;
use App\Services\Ai\Agents\Events\AgentResponseReceivedEvent;
use App\Services\Ai\Agents\Events\AgentSendingEvent;
use App\Services\Ai\Agents\Events\AgentStreamCompletedEvent;
use App\Services\Ai\Agents\Events\AgentStreamInitiatedEvent;
use App\Services\Ai\Agents\Exceptions\AgentStateException;
use App\Services\Ai\LaravelAi\AgentRequestScope;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Services\Ai\Values\TokenUsage;
use Laravel\Ai\Contracts\Agent as LaravelAgentInterface;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
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
    use Promptable;

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
     * The total timeout in seconds for requests of this agent, replacing the Laravel AI package's
     * hardcoded default of 60.
     *
     * The Laravel AI package calls this method while it resolves the prompt timeout, before its own
     * fallback applies ({@see \Laravel\Ai\Promptable::getTimeout()}). The value comes from the
     * provider's driver configuration: `model_providers.request_timeout` globally, overridable per
     * provider via its adapter settings. A value of 0 removes the timeout entirely.
     */
    protected function timeout(): int
    {
        $timeout = $this->getContext()->provider->driver->additionalConfiguration()['request_timeout'] ?? null;
        return $timeout === null ? 60 : (int)$timeout;
    }

    /**
     * The stall timeout in seconds of this agent's provider, null when stall detection is disabled.
     * Applied by {@see StallDetectionMiddleware} to the HTTP requests of the agent, see
     * `model_providers.stall_timeout`.
     */
    protected function getStallSeconds(): int|null
    {
        $stall = $this->getContext()->provider->driver->additionalConfiguration()['stall_timeout'] ?? null;
        return $stall === null ? null : (int)$stall;
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
        AgentSendingEvent::dispatch($this, $this->getContext(), $this->getContext()->provider);

        $response = AgentRequestScope::run(
            fn() => $this->prompt(
                prompt: $this->getPromptString(),
                attachments: $this->getAttachments(),
                provider: (string)ProviderDriverPortal::fromProviderProxy($this->getContext()->provider),
                model: $this->getContext()->model->model_id
            ),
            $this->getStallSeconds()
        );

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
        AgentSendingEvent::dispatch($this, $this->getContext(), $this->getContext()->provider);

        $response = AgentRequestScope::run(
            fn() => $this->stream(
                prompt: $this->getPromptString(),
                attachments: $this->getAttachments(),
                provider: (string)ProviderDriverPortal::fromProviderProxy($this->getContext()->provider),
                model: $this->getContext()->model->model_id
            ),
            $this->getStallSeconds()
        );

        $response->then(function (AgentResponse $response) {
            $this->usage = $response->usage;

            AgentStreamCompletedEvent::dispatch($this, $this->getContext(), $this->getContext()->provider, $response, $response->usage);
        });

        AgentStreamInitiatedEvent::dispatch($this, $this->getContext(), $this->getContext()->provider, $response);

        return $response;
    }
}
