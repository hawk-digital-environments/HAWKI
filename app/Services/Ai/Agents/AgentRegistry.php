<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Exceptions\AgentNotResolvedException;
use App\Services\Ai\Agents\Exceptions\InvalidAgentFactoryClassException;
use App\Utils\Lists\LazySingletonList;
use App\Utils\Lists\TopSortStringList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central registry that maps incoming requests to the {@see AgentInterface} implementation
 * capable of handling them.
 *
 * Factories are registered via {@see declare()} and iterated in topological order — earlier
 * factories take precedence. The first factory that returns a non-null agent from
 * {@see AgentFactoryInterface::createAgent()} wins; remaining factories are skipped.
 *
 * The registry is a singleton whose factory registrations are wired up in
 * {@see AiServiceProvider} using `$app->extend(AgentRegistry::class, ...)`.
 *
 * Usage (service provider):
 * ```php
 * $this->app->extend(
 *     AgentRegistry::class,
 *     fn(AgentRegistry $registry) => $registry
 *         ->declare(ChatAgentFromLegacyRequestFactory::class)
 * );
 * ```
 *
 * Usage (application code):
 * ```php
 * $agent = $this->agentRegistry->getAgent($request);
 * $agent->send();
 * ```
 *
 * @api
 */
#[Singleton]
readonly class AgentRegistry
{
    /**
     * @var TopSortStringList<class-string<AgentFactoryInterface>>
     */
    private TopSortStringList $factoryClasses;

    public function __construct(
        /**
         * @var LazySingletonList<class-string<AgentFactoryInterface>, AgentFactoryInterface>
         */
        #[Give(AiServiceProvider::AGENT_FACTORY_LIST)]
        private LazySingletonList $agentFactories
    )
    {
        $this->factoryClasses = new TopSortStringList();
    }

    /**
     * Registers an agent factory class with optional ordering constraints.
     *
     * The class must implement {@see AgentFactoryInterface}; an
     * {@see InvalidAgentFactoryClassException} is thrown otherwise. Calling declare() with
     * the same class more than once is safe and only accumulates additional ordering rules.
     *
     * @param class-string<AgentFactoryInterface> $agentFactoryClass
     * @param array<class-string>|class-string|null $before Factory classes this one must run before.
     * @param array<class-string>|class-string|null $after  Factory classes this one must run after.
     * @throws InvalidAgentFactoryClassException when $agentFactoryClass does not implement AgentFactoryInterface.
     */
    public function declare(
        string            $agentFactoryClass,
        array|string|null $before = null,
        array|string|null $after = null
    ): self
    {
        if (!is_a($agentFactoryClass, AgentFactoryInterface::class, true)) {
            throw InvalidAgentFactoryClassException::forClass($agentFactoryClass);
        }

        $this->factoryClasses->add($agentFactoryClass, $before, $after);

        return $this;
    }

    /**
     * Iterates registered factories in priority order and returns the first agent produced,
     * or null when no factory accepts the request.
     */
    public function tryToGetAgent(mixed $request): ?AgentInterface
    {
        foreach ($this->factoryClasses as $factoryClass) {
            $agent = $this->agentFactories->get($factoryClass)->createAgent($request);
            if ($agent !== null) {
                return $agent;
            }
        }
        return null;
    }

    /**
     * Returns the first agent that accepts the request.
     *
     * @throws AgentNotResolvedException when no registered factory can handle the request.
     */
    public function getAgent(mixed $request): AgentInterface
    {
        $agent = $this->tryToGetAgent($request);
        if (!$agent) {
            throw AgentNotResolvedException::forRequestType(get_debug_type($request));
        }
        return $agent;
    }
}
