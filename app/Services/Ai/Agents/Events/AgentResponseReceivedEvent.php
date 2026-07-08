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
 * Dispatched after an agent has received a complete synchronous response from the AI provider.
 *
 * This event fires at the end of {@see AgentInterface::send()}, after token usage has been
 * recorded on the agent. It is NOT dispatched for streaming calls; see
 * {@see AgentStreamCompletedEvent} for the streaming equivalent.
 *
 * Listeners can use this event to:
 * - Log or persist the full response text and token usage.
 * - Record latency metrics for a completed non-streaming request.
 * - Trigger post-processing workflows that depend on the final response.
 */
readonly class AgentResponseReceivedEvent
{
    use Dispatchable;

    public function __construct(
        /** The agent that sent the request. */
        public AgentInterface     $agent,
        /** The request context containing the resolved model, provider, and parameters. */
        public AgentRequestContext $context,
        /** The provider the request was sent to. */
        public AiProviderProxy    $provider,
        /** The complete response returned by the AI provider. */
        public AgentResponse      $response,
        /** Token usage reported by the provider for this request. */
        public Usage              $usage,
    )
    {
    }
}
