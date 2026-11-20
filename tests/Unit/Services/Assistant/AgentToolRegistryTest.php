<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\AgentToolRegistry;
use App\Services\Assistant\Contracts\AgentTool;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Assistant\AgentToolRegistryTest\AgentToolRegistryTestFixtures\RegistryFixtureAgentTool;

#[CoversClass(AgentToolRegistry::class)]
class AgentToolRegistryTest extends TestCase
{
    public function testItConstructs(): void
    {
        static::assertInstanceOf(AgentToolRegistry::class, new AgentToolRegistry($this->app));
    }

    public function testItDeclaresAndResolvesImplementations(): void
    {
        $registry = new AgentToolRegistry($this->app);

        $registry->declare(WellKnownCapabilities::KNOWLEDGE_BASE, RegistryFixtureAgentTool::class);

        static::assertTrue($registry->has(WellKnownCapabilities::KNOWLEDGE_BASE));
        static::assertSame([WellKnownCapabilities::KNOWLEDGE_BASE], $registry->keys());
        static::assertInstanceOf(RegistryFixtureAgentTool::class, $registry->get(WellKnownCapabilities::KNOWLEDGE_BASE));
    }

    public function testItResolvesImplementationsFromTheContainer(): void
    {
        $stub = new RegistryFixtureAgentTool();
        $this->app->instance(RegistryFixtureAgentTool::class, $stub);

        $registry = (new AgentToolRegistry($this->app))
            ->declare(WellKnownCapabilities::KNOWLEDGE_BASE, RegistryFixtureAgentTool::class);

        static::assertSame($stub, $registry->get(WellKnownCapabilities::KNOWLEDGE_BASE));
        static::assertSame([$stub], $registry->all());
    }

    public function testItReturnsNullForUndeclaredKeys(): void
    {
        $registry = new AgentToolRegistry($this->app);

        static::assertFalse($registry->has(WellKnownCapabilities::KNOWLEDGE_BASE));
        static::assertNull($registry->get(WellKnownCapabilities::KNOWLEDGE_BASE));
        static::assertSame([], $registry->keys());
        static::assertSame([], $registry->all());
    }

    public function testTheLastDeclarationOfAKeyWins(): void
    {
        $first = new RegistryFixtureAgentTool();
        $second = new RegistryFixtureAgentTool();
        $this->app->forgetInstance(RegistryFixtureAgentTool::class);

        $registry = new AgentToolRegistry($this->app);
        $registry->declare(WellKnownCapabilities::KNOWLEDGE_BASE, RegistryFixtureAgentTool::class);

        $this->app->instance(RegistryFixtureAgentTool::class, $first);
        static::assertSame($first, $registry->get(WellKnownCapabilities::KNOWLEDGE_BASE));

        $this->app->forgetInstance(RegistryFixtureAgentTool::class);
        $this->app->instance(RegistryFixtureAgentTool::class, $second);
        static::assertSame($second, $registry->get(WellKnownCapabilities::KNOWLEDGE_BASE));
    }

    public function testItIteratesKeyToImplementationPairs(): void
    {
        $this->app->instance(RegistryFixtureAgentTool::class, new RegistryFixtureAgentTool());

        $registry = (new AgentToolRegistry($this->app))
            ->declare(WellKnownCapabilities::KNOWLEDGE_BASE, RegistryFixtureAgentTool::class);

        $iterated = [];

        foreach ($registry as $key => $tool) {
            static::assertInstanceOf(AgentTool::class, $tool);
            $iterated[] = $key;
        }

        static::assertSame([WellKnownCapabilities::KNOWLEDGE_BASE], $iterated);
    }

    public function testItRejectsNonExistentImplementationClasses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        (new AgentToolRegistry($this->app))->declare('some_key', '\\App\\Nonexistent\\AgentTool');
    }

    public function testItRejectsClassesNotImplementingTheContract(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        (new AgentToolRegistry($this->app))->declare('some_key', \stdClass::class);
    }
}
