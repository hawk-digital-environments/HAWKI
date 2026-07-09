<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Events;

use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Dispatched when a streaming agent response has been fully consumed by the client.
 *
 * This event fires inside the {@see \Laravel\Ai\Responses\StreamableAgentResponse::then()}
 * callback of {@see AgentInterface::sendStreaming()}, after all tokens have been received
 * and token usage has been recorded on the agent.
 *
 * Listeners can use this event to:
 * - Log or persist the complete streamed response and token usage.
 * - Record end-to-end latency for streaming requests.
 * - Trigger post-processing workflows once all streamed content has been delivered.
 */
readonly class AgentStreamCompletedEvent
{
    use Dispatchable;

    public function __construct(
        /** The agent that sent the streaming request. */
        public AgentInterface     $agent,
        /** The request context containing the resolved model, provider, and parameters. */
        public AgentRequestContext $context,
        /** The provider the request was sent to. */
        public AiProviderProxy    $provider,
        /** The completed response containing the full content accumulated from the stream. */
        public AgentResponse      $response,
        /** Token usage reported by the provider once the stream was fully consumed. */
        public Usage              $usage,
    )
    {
    }
}
