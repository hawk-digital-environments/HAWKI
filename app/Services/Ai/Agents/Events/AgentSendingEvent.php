<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Events;

use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched immediately before an agent sends a request to the AI provider.
 *
 * This event fires for both synchronous ({@see AgentInterface::send()}) and streaming
 * ({@see AgentInterface::sendStreaming()}) calls, before any HTTP communication takes place.
 *
 * Listeners can use this event to:
 * - Log or trace the start of an agent request including the model and provider used.
 * - Start timers or counters for request-level performance metrics.
 * - Validate that the agent context meets any pre-send requirements.
 */
readonly class AgentSendingEvent
{
    use Dispatchable;

    public function __construct(
        /** The agent that is about to send a request. */
        public AgentInterface     $agent,
        /** The request context containing the resolved model, provider, and parameters. */
        public AgentRequestContext $context,
        /** The provider the request will be sent to. */
        public AiProviderProxy    $provider,
    )
    {
    }
}
