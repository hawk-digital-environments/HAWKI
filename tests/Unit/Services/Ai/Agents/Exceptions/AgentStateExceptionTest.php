<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\AgentStateException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AgentStateException::class)]
class AgentStateExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsRuntimeException(): void
    {
        $sut = AgentStateException::forUsageNotAvailable();
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = AgentStateException::forUsageNotAvailable();
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    public function testItForUsageNotAvailableMatchesExpectedMessage(): void
    {
        $sut = AgentStateException::forUsageNotAvailable();
        static::assertSame(
            'Token usage is not available. Call send() or sendStreaming() before reading usage.',
            $sut->getMessage()
        );
    }
}
