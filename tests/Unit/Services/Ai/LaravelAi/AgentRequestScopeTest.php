<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi;

use App\Services\Ai\LaravelAi\AgentRequestScope;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AgentRequestScope::class)]
class AgentRequestScopeTest extends TestCase
{
    public function testItRunsTheCallbackInsideTheScope(): void
    {
        $seenInside = null;

        $result = AgentRequestScope::run(function () use (&$seenInside) {
            $seenInside = [AgentRequestScope::isActive(), AgentRequestScope::stallSeconds()];
            return 'result';
        }, 120);

        static::assertSame('result', $result);
        static::assertSame([true, 120], $seenInside);
    }

    public function testItLeavesTheScopeAfterTheCallback(): void
    {
        AgentRequestScope::run(static fn() => null, 120);

        static::assertFalse(AgentRequestScope::isActive());
        static::assertNull(AgentRequestScope::stallSeconds());
    }

    public function testItLeavesTheScopeWhenTheCallbackThrows(): void
    {
        try {
            AgentRequestScope::run(static fn() => throw new \RuntimeException('agent request failed'), 120);
            static::fail('The exception of the callback was not propagated');
        } catch (\RuntimeException $e) {
            static::assertSame('agent request failed', $e->getMessage());
        }

        static::assertFalse(AgentRequestScope::isActive());
        static::assertNull(AgentRequestScope::stallSeconds());
    }
}
