<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents;

use App\Services\Ai\Agents\AgentRegistry;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Exceptions\AgentNotResolvedException;
use App\Services\Ai\Agents\Exceptions\InvalidAgentFactoryClassException;
use App\Utils\Lists\LazySingletonList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AgentRegistry::class)]
class AgentRegistryTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates an AgentRegistry backed by a LazySingletonList that resolves factory class
     * names to the provided mock factory instances.
     *
     * @param array<class-string, AgentFactoryInterface> $factoryMap
     */
    private function makeRegistry(array $factoryMap = []): AgentRegistry
    {
        $list = new LazySingletonList(
            keyGenerator: fn(string $class) => $class,
            factory: fn(string $class) => $factoryMap[$class]
                ?? throw new \LogicException("Unexpected factory class: $class")
        );

        return new AgentRegistry($list);
    }

    private function makeFactory(AgentInterface|null $returnAgent): AgentFactoryInterface&MockObject
    {
        $factory = $this->createMock(AgentFactoryInterface::class);
        $factory->method('createAgent')->willReturn($returnAgent);
        return $factory;
    }

    private function makeAgent(): AgentInterface&MockObject
    {
        return $this->createMock(AgentInterface::class);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $list = new LazySingletonList(
            keyGenerator: fn(string $c) => $c,
            factory: fn(string $c) => $this->createMock(AgentFactoryInterface::class)
        );

        $sut = new AgentRegistry($list);
        static::assertInstanceOf(AgentRegistry::class, $sut);
    }

    // =========================================================================
    // declare — validation
    // =========================================================================

    public function testItThrowsWhenDeclaringClassThatDoesNotImplementAgentFactoryInterface(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(InvalidAgentFactoryClassException::class);
        $this->expectExceptionMessage(\stdClass::class);

        $sut->declare(\stdClass::class);
    }

    public function testItReturnsSelfFromDeclare(): void
    {
        $factory = $this->makeFactory(null);
        $factoryClass = get_class($factory);

        $sut = $this->makeRegistry([$factoryClass => $factory]);
        static::assertSame($sut, $sut->declare($factoryClass));
    }

    // =========================================================================
    // tryToGetAgent
    // =========================================================================

    public function testItReturnsNullWhenNoFactoriesAreRegistered(): void
    {
        $sut = $this->makeRegistry();
        static::assertNull($sut->tryToGetAgent(['some' => 'request']));
    }

    public function testItReturnsNullWhenNoFactoryAcceptsTheRequest(): void
    {
        $factory = $this->makeFactory(null);
        $factoryClass = get_class($factory);

        $sut = $this->makeRegistry([$factoryClass => $factory]);
        $sut->declare($factoryClass);

        static::assertNull($sut->tryToGetAgent('unrecognised request'));
    }

    public function testItReturnsAgentFromFirstMatchingFactory(): void
    {
        $agent = $this->makeAgent();
        $factory = $this->makeFactory($agent);
        $factoryClass = get_class($factory);

        $sut = $this->makeRegistry([$factoryClass => $factory]);
        $sut->declare($factoryClass);

        static::assertSame($agent, $sut->tryToGetAgent(['request']));
    }

    public function testItSkipsFactoriesThatReturnNull(): void
    {
        $nullFactory = $this->makeFactory(null);
        $agent = $this->makeAgent();
        $agentFactory = $this->makeFactory($agent);

        // Two different anonymous-class instances have different class names
        $nullClass = get_class($nullFactory);
        $agentClass = get_class($agentFactory);

        $sut = $this->makeRegistry([
            $nullClass  => $nullFactory,
            $agentClass => $agentFactory,
        ]);
        $sut->declare($nullClass);
        $sut->declare($agentClass);

        static::assertSame($agent, $sut->tryToGetAgent(['request']));
    }

    // =========================================================================
    // getAgent
    // =========================================================================

    public function testItThrowsWhenNoFactoryProvidesAnAgent(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(AgentNotResolvedException::class);
        $this->expectExceptionMessage('"array"');

        $sut->getAgent([]);
    }

    public function testItReturnsAgentWhenFactoryMatches(): void
    {
        $agent = $this->makeAgent();
        $factory = $this->makeFactory($agent);
        $factoryClass = get_class($factory);

        $sut = $this->makeRegistry([$factoryClass => $factory]);
        $sut->declare($factoryClass);

        static::assertSame($agent, $sut->getAgent(['request']));
    }

    public function testItIncludesRequestTypeInExceptionMessage(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(AgentNotResolvedException::class);
        $this->expectExceptionMessage('"string"');

        $sut->getAgent('some string request');
    }
}
