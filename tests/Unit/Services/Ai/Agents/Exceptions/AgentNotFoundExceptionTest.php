<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\AgentNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AgentNotFoundException::class)]
class AgentNotFoundExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsInvalidArgumentException(): void
    {
        $sut = AgentNotFoundException::forAgentKey('chat');
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = AgentNotFoundException::forAgentKey('chat');
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    public function testItForAgentKeyContainsTheKey(): void
    {
        $sut = AgentNotFoundException::forAgentKey('my-agent');
        static::assertStringContainsString('my-agent', $sut->getMessage());
    }

    public function testItForAgentKeyMatchesExpectedMessage(): void
    {
        $sut = AgentNotFoundException::forAgentKey('chat');
        static::assertSame('Agent "chat" not found in registry.', $sut->getMessage());
    }
}
