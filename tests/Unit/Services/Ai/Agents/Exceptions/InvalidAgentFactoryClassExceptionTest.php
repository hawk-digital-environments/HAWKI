<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\InvalidAgentFactoryClassException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidAgentFactoryClassException::class)]
class InvalidAgentFactoryClassExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsInvalidArgumentException(): void
    {
        $sut = InvalidAgentFactoryClassException::forClass('SomeClass');
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = InvalidAgentFactoryClassException::forClass('SomeClass');
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forClass
    // =========================================================================

    public function testItForClassContainsTheClassName(): void
    {
        $sut = InvalidAgentFactoryClassException::forClass('App\\My\\Factory');
        static::assertStringContainsString('App\\My\\Factory', $sut->getMessage());
    }

    public function testItForClassContainsTheRequiredInterface(): void
    {
        $sut = InvalidAgentFactoryClassException::forClass('SomeClass');
        static::assertStringContainsString(AgentFactoryInterface::class, $sut->getMessage());
    }

    public function testItForClassMatchesExpectedMessage(): void
    {
        $sut = InvalidAgentFactoryClassException::forClass('SomeClass');
        static::assertSame(
            sprintf(
                'Agent factory class "%s" must implement %s.',
                'SomeClass',
                AgentFactoryInterface::class
            ),
            $sut->getMessage()
        );
    }
}
