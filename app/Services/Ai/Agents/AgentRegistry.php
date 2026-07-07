<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Utils\Lists\LazySingletonList;
use App\Utils\Lists\TopSortStringList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

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

    public function declare(
        string            $agentFactoryClass,
        array|string|null $before = null,
        array|string|null $after = null
    ): self
    {
        if (!is_a($agentFactoryClass, AgentFactoryInterface::class, true)) {
            // @todo better exception
            throw new \InvalidArgumentException(sprintf(
                'Agent factory class %s must implement %s',
                $agentFactoryClass,
                AgentFactoryInterface::class
            ));
        }

        $this->factoryClasses->add($agentFactoryClass, $before, $after);

        return $this;
    }

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

    public function getAgent(mixed $request): AgentInterface
    {
        $agent = $this->tryToGetAgent($request);
        if (!$agent) {
            // @todo better exception
            throw new \RuntimeException(sprintf(
                'No agent factory could create an agent for request of type %s',
                get_debug_type($request)
            ));
        }
        return $agent;
    }
}
