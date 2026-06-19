<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent;

use App\Services\Ai\Agent\Contracts\AgentRequestFactoryInterface;
use App\Services\Ai\Agent\Contracts\AgentRequestInterface;
use App\Services\Ai\Agent\Exceptions\AgentNotFoundException;
use App\Services\Ai\Registries\AgentRegistry;
use App\Services\Ai\Values\WellKnownAgents;
use Illuminate\Container\Attributes\Singleton;

/**
 * The main router for creating agent requests for your agents.
 */
#[Singleton]
readonly class AgentRequestFactory implements AgentRequestFactoryInterface
{
    public function __construct(
        private AgentRegistry $agentRegistry
    )
    {
    }

    public function createFromPayload(array $payload): AgentRequestInterface
    {
        $agent = $payload['agent'] ?? WellKnownAgents::CHAT;
        if (!$this->agentRegistry->has($agent)) {
            throw AgentNotFoundException::forAgentKey($agent);
        }

        return $this->agentRegistry->getRequestFactory($agent)->createFromPayload($payload);
    }
}
