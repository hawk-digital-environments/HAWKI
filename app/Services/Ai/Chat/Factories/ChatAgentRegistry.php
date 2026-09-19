<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Factories;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Chat\Exceptions\ChatAgentNotResolvedException;
use App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Utils\Lists\LazySingletonList;
use App\Utils\Lists\TopSortStringList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central registry that maps an {@see AiRequest} to the {@see AgentInterface} capable of
 * handling it — the AiRequest-typed successor of {@see \App\Services\Ai\Agents\AgentRegistry}.
 *
 * Factories are registered via {@see declare()} and iterated in topological order —
 * earlier factories take precedence. The first factory that returns a non-null agent
 * from {@see ChatAgentFactoryInterface::createAgent()} wins.
 *
 * The registry is a singleton whose factory registrations are wired up in
 * {@see AiServiceProvider} using `$app->extend(ChatAgentRegistry::class, ...)`.
 *
 * @api
 */
#[Singleton()]
readonly class ChatAgentRegistry
{
    /**
     * @var TopSortStringList<class-string<ChatAgentFactoryInterface>>
     */
    private TopSortStringList $factoryClasses;

    public function __construct(
        /**
         * @var LazySingletonList<class-string<ChatAgentFactoryInterface>, ChatAgentFactoryInterface>
         */
        #[Give(AiServiceProvider::CHAT_AGENT_FACTORY_LIST)]
        private LazySingletonList $agentFactories,
    ) {
        $this->factoryClasses = new TopSortStringList();
    }

    /**
     * Registers a chat agent factory class with optional ordering constraints.
     *
     * @param class-string<ChatAgentFactoryInterface> $factoryClass
     * @param null|class-string|list<class-string>    $before       factory classes this one must run before
     * @param null|class-string|list<class-string>    $after        factory classes this one must run after
     */
    public function declare(
        string $factoryClass,
        null|array|string $before = null,
        null|array|string $after = null,
    ): self {
        $this->factoryClasses->add($factoryClass, $before, $after);

        return $this;
    }

    /**
     * Iterates registered factories in priority order and returns the first agent produced,
     * or null when no factory accepts the request.
     */
    public function tryToGetAgent(AiRequest $request): ?AgentInterface
    {
        foreach ($this->factoryClasses as $factoryClass) {
            $agent = $this->agentFactories->get($factoryClass)->createAgent($request);

            if (null !== $agent) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Returns the first agent that accepts the request.
     *
     * @throws ChatAgentNotResolvedException when no registered factory can handle the request
     */
    public function getAgent(AiRequest $request): AgentInterface
    {
        $agent = $this->tryToGetAgent($request);

        if (!$agent) {
            throw ChatAgentNotResolvedException::forMissingDefaultModel();
        }

        return $agent;
    }
}
