<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Events;

use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * Dispatched after a streaming agent request has been initiated but before the stream
 * has been consumed.
 *
 * This event fires at the end of {@see AgentInterface::sendStreaming()}, after the
 * {@see StreamableAgentResponse} has been created and the completion callback registered.
 * The stream has not started producing tokens yet when this event fires.
 *
 * Note: token usage is not available at this point. Subscribe to
 * {@see AgentStreamCompletedEvent} to receive usage data once the stream is fully consumed.
 *
 * Listeners can use this event to:
 * - Log that a streaming request has been dispatched to the provider.
 * - Attach additional callbacks to the streamable response before it is consumed.
 * - Start latency timers for streaming requests.
 */
readonly class AgentStreamInitiatedEvent
{
    use Dispatchable;

    public function __construct(
        /** The agent that initiated the streaming request. */
        public AgentInterface        $agent,
        /** The request context containing the resolved model, provider, and parameters. */
        public AgentRequestContext   $context,
        /** The provider the request was sent to. */
        public AiProviderProxy       $provider,
        /** The streaming response object. The stream has not started producing tokens yet. */
        public StreamableAgentResponse $response,
    )
    {
    }
}
