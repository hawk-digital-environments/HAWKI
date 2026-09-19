<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat;

use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Chat\Factories\ChatAgentRegistry;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;
use Illuminate\Container\Attributes\Singleton;

/**
 * Chat-scoped domain service: resolves an agent for an {@see AiRequest} and executes it,
 * normalizing vendor results into the chat IR.
 *
 * This is the programmatic entry point for chat — HTTP controllers delegate here, and
 * any PHP code (jobs, commands, future orchestrators) can call {@see send()} /
 * {@see sendStreaming()} directly without the HTTP/formatter layer.
 *
 * Token usage is recorded by the {@see \App\Services\Ai\Listeners\RecordChatUsageListener}
 * event listeners for every invocation whose {@see \App\Services\Ai\Agents\Values\AgentRequestContext}
 * was built with usage recording enabled — which this service's factory chain does.
 *
 * @api
 */
#[Singleton()]
readonly class ChatService
{
    public function __construct(
        private ChatAgentRegistry $agentRegistry,
        private AiStreamNormalizerFactory $normalizerFactory,
    ) {
    }

    /**
     * Returns the agent that handles the given request.
     */
    public function getAgent(AiRequest $request): AgentInterface
    {
        return $this->agentRegistry->getAgent($request);
    }

    /**
     * Executes the request synchronously and returns the normalized response IR.
     */
    public function send(AiRequest $request): AiResponse
    {
        $agent = $this->getAgent($request);
        $response = $agent->send();

        return $this->normalizerFactory->create()->normalizeResponse($response);
    }

    /**
     * Executes the request in streaming mode and returns the normalized IR event stream.
     *
     * The returned iterable is lazy — vendor events are only requested (and the upstream
     * HTTP call only driven) as the consumer iterates.
     *
     * @return iterable<int, AiStreamEvent>
     */
    public function sendStreaming(AiRequest $request): iterable
    {
        $agent = $this->getAgent($request);
        $vendorEvents = $agent->sendStreaming();

        return $this->normalizerFactory->create()->normalizeStream($vendorEvents);
    }
}
