<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Adapters\AbstractLaravelAgentTimeoutTest\AbstractLaravelAgentTimeoutTestFixtures;

use App\Services\Ai\Agents\Adapters\AbstractLaravelAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Agents\Values\AgentTimeouts;
use Stringable;

/**
 * Minimal concrete agent that exposes the protected timeout resolution of {@see AbstractLaravelAgent}.
 */
class TimeoutExposingAgent extends AbstractLaravelAgent
{
    public function __construct(
        private readonly AgentRequestContext $testContext
    )
    {
    }

    public function getContext(): AgentRequestContext
    {
        return $this->testContext;
    }

    public function instructions(): Stringable|string
    {
        return 'test instructions';
    }

    protected function getPromptString(): string
    {
        return 'test prompt';
    }

    public function exposeTimeouts(): AgentTimeouts
    {
        return $this->getTimeouts();
    }
}
