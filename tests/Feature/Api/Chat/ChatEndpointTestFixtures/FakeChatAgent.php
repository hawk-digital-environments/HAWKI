<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Chat\ChatEndpointTestFixtures;

use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Values\TokenUsage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEvent;

/**
 * Deterministic agent double for the chat endpoint feature tests: replays a scripted
 * list of vendor stream events (streaming mode) or returns a fixed response
 * (non-streaming mode).
 */
class FakeChatAgent implements AgentInterface
{
    public function __construct(
        private readonly array $vendorEvents = [],
        private readonly ?AgentResponse $response = null,
    ) {
    }

    public function getContext(): AgentRequestContext
    {
        throw new \RuntimeException('The fake chat agent does not support contexts.');
    }

    public function getUsage(): TokenUsage
    {
        throw new \RuntimeException('The fake chat agent does not support usage.');
    }

    public function send(): AgentResponse
    {
        return $this->response ?? new AgentResponse(
            invocationId: 'fake-invocation',
            text: 'ok',
            usage: new Usage(promptTokens: 2, completionTokens: 3),
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        );
    }

    public function sendStreaming(): StreamableAgentResponse
    {
        $events = $this->vendorEvents;

        return new StreamableAgentResponse(
            invocationId: 'fake-invocation',
            generator: static function () use ($events): \Generator {
                foreach ($events as $event) {
                    if ($event instanceof StreamEvent) {
                        yield $event;
                    }
                }
            },
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        );
    }
}
