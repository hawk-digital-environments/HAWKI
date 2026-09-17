<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi;

use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Tools\Exceptions\ToolAccessException;
use Illuminate\Container\Attributes\Singleton;

/** A denial ends this agent turn even when the SDK converts a tool exception into text. */
#[Singleton]
final class ToolExecutionState
{
    private \WeakMap $failures;

    public function __construct()
    {
        $this->failures = new \WeakMap();
    }

    public function check(AgentRequestContext $context): void
    {
        if (isset($this->failures[$context])) {
            throw $this->failures[$context] === 'TOOL_ACCESS_DENIED'
                ? ToolAccessException::denied() : ToolAccessException::unavailable();
        }
    }

    public function deny(AgentRequestContext $context, ToolAccessException $exception): never
    {
        $this->failures[$context] = $exception->errorCode;
        throw $exception;
    }
}
