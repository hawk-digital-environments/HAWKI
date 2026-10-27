<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Adapters\AbstractLaravelAgentTimeoutTest\AbstractLaravelAgentTimeoutTestFixtures;

use App\Services\Ai\Agents\Adapters\AbstractLaravelAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Stringable;

/**
 * Minimal concrete agent that records the timeout {@see AbstractLaravelAgent} passes to the
 * Laravel AI package, instead of sending a real request.
 */
class TimeoutExposingAgent extends AbstractLaravelAgent
{
    public int|null $promptTimeout = null;
    public int|null $streamTimeout = null;

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

    public function prompt(
        Decisions|string      $prompt,
        array                 $attachments = [],
        Lab|array|string|null $provider = null,
        ?string               $model = null,
        ?int                  $timeout = null
    ): AgentResponse
    {
        $this->promptTimeout = $timeout;
        return new AgentResponse('invocation', 'text', new Usage(), new Meta());
    }

    public function stream(
        Decisions|string      $prompt,
        array                 $attachments = [],
        Lab|array|string|null $provider = null,
        ?string               $model = null,
        ?int                  $timeout = null
    ): StreamableAgentResponse
    {
        $this->streamTimeout = $timeout;
        return new StreamableAgentResponse('invocation', static fn() => yield from []);
    }
}
