<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\AgentNotResolvedException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AgentNotResolvedException::class)]
class AgentNotResolvedExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsRuntimeException(): void
    {
        $sut = AgentNotResolvedException::forRequestType('array');
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = AgentNotResolvedException::forRequestType('array');
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    public function testItForRequestTypeContainsTheType(): void
    {
        $sut = AgentNotResolvedException::forRequestType('stdClass');
        static::assertStringContainsString('stdClass', $sut->getMessage());
    }

    public function testItForRequestTypeMatchesExpectedMessage(): void
    {
        $sut = AgentNotResolvedException::forRequestType('array');
        static::assertSame(
            'No agent factory could create an agent for request of type "array".',
            $sut->getMessage()
        );
    }
}
