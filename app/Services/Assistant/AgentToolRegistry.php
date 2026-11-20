<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Services\Assistant\Contracts\AgentTool;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Foundation\Application;
use Traversable;

/**
 * Registry of {@see AgentTool} implementations — the ambient capabilities
 * modules grant to assistant agent runs.
 *
 * Each capability key maps to one implementation class; instances are
 * resolved from the container lazily on first access, so declaring a tool
 * never triggers instantiation. Modules declare their tools from their
 * service provider, the same pattern as the capability and adapter
 * registries (see the Plugin System Preview article):
 *
 * ```php
 * $this->app->extend(
 *     AgentToolRegistry::class,
 *     fn(AgentToolRegistry $registry) => $registry
 *         ->declare(WellKnownCapabilities::KNOWLEDGE_BASE, RagKnowledgeAgentTool::class),
 * );
 * ```
 *
 * @implements \IteratorAggregate<string, AgentTool>
 * @api
 */
#[Singleton]
class AgentToolRegistry implements \IteratorAggregate
{
    /** @var array<string, class-string<AgentTool>> */
    private array $implementations = [];

    public function __construct(
        private readonly Application $container,
    ) {
    }

    /**
     * Registers the implementation serving a capability key. Declaring an
     * already-declared key replaces the earlier declaration (the last
     * declaration wins), so an overlay can swap a module's tool without
     * depending on provider boot order.
     *
     * @param string $key a {@see \App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities} constant
     * @param class-string<AgentTool> $implementation
     *
     * @return $this for fluent chaining of multiple declarations
     *
     * @throws \InvalidArgumentException when the implementation does not exist or does not implement {@see AgentTool}
     */
    public function declare(string $key, string $implementation): self
    {
        if (!class_exists($implementation)) {
            throw new \InvalidArgumentException(sprintf('Agent tool class "%s" does not exist.', $implementation));
        }

        if (!is_subclass_of($implementation, AgentTool::class)) {
            throw new \InvalidArgumentException(sprintf('Agent tool class "%s" must implement %s.', $implementation, AgentTool::class));
        }

        $this->implementations[$key] = $implementation;

        return $this;
    }

    /** Returns true when an implementation is declared under the given key. */
    public function has(string $key): bool
    {
        return isset($this->implementations[$key]);
    }

    /**
     * All declared capability keys, in declaration order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->implementations);
    }

    /**
     * The implementation declared for a key, resolved from the container,
     * or null when the key is not declared. Implementations marked
     * `#[Singleton]` resolve to the container's shared instance.
     */
    public function get(string $key): ?AgentTool
    {
        if (!isset($this->implementations[$key])) {
            return null;
        }

        return $this->container->make($this->implementations[$key]);
    }

    /**
     * All declared implementations, resolved from the container in
     * declaration order.
     *
     * @return list<AgentTool>
     */
    public function all(): array
    {
        $tools = [];

        foreach ($this->keys() as $key) {
            $tool = $this->get($key);

            if ($tool !== null) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Yields capability key => implementation for every declaration.
     *
     * @return Traversable<string, AgentTool>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->keys() as $key) {
            $tool = $this->get($key);

            if ($tool !== null) {
                yield $key => $tool;
            }
        }
    }
}
