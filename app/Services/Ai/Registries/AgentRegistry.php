<?php
declare(strict_types=1);


namespace App\Services\Ai\Registries;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Agent\AgentRequestFactory;
use App\Services\Ai\Agent\Contracts\AgentInterface;
use App\Services\Ai\Agent\Contracts\AgentRequestFactoryInterface;
use App\Services\Ai\Agent\Contracts\AgentRequestInterface;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class AgentRegistry
{
    /**
     * @var array<string, class-string<AgentInterface>>
     */
    private array $agentClasses = [];
    /**
     * @var array<string, class-string<AgentRequestFactory>> Agent key to request factory class mapping
     */
    private array $agentRequestFactoryClasses = [];
    /**
     * @var array<class-string<AgentRequestInterface>, string> Agent request class to agent key mapping
     */
    private array $agentRequestClasses = [];

    public function __construct(
        /**
         * @var LazySingletonList<class-string<AgentInterface>, AgentInterface>
         */
        #[Give(AiServiceProvider::AGENT_LIST)]
        private readonly LazySingletonList $agents,
        /**
         * @var LazySingletonList<class-string<AgentRequestFactoryInterface>, AgentRequestFactoryInterface>
         */
        #[Give(AiServiceProvider::AGENT_REQUEST_FACTORY_LIST)]
        private readonly LazySingletonList $agentRequestFactories
    )
    {

    }

    public function declare(
        string       $agentKey,
        string       $agentClass,
        string|array $agentRequestClass,
        string       $agentRequestFactoryClass
    ): self
    {
        if (!is_a($agentClass, AgentInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Agent class %s must implement %s',
                $agentClass,
                AgentInterface::class
            ));
        }
        if (!is_a($agentRequestFactoryClass, AgentRequestFactoryInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Agent request factory class %s must implement %s',
                $agentRequestFactoryClass,
                AgentRequestFactoryInterface::class
            ));
        }
        $agentRequestClasses = is_array($agentRequestClass) ? $agentRequestClass : [$agentRequestClass];
        $atLeastOneRequestClassExists = false;
        foreach ($agentRequestClasses as $requestClass) {
            // There might be plugins that want to eagerly listen for various agent request classes of other plugins
            // but in that case it might occur that the other plugin is not installed, so we want to allow non-existing classes
            // as long as at least one of the given request classes exists.
            if (!class_exists($requestClass)) {
                continue;
            }
            $atLeastOneRequestClassExists = true;
            if (!is_a($requestClass, AgentRequestInterface::class, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Agent request class %s must implement %s',
                    $requestClass,
                    AgentRequestInterface::class
                ));
            }
            $this->agentRequestClasses[$requestClass] = $agentKey;
        }
        if (!$atLeastOneRequestClassExists) {
            throw new \InvalidArgumentException(sprintf(
                'At least one of the given agent request classes must exist for agent key %s',
                $agentKey
            ));
        }
        $this->agentClasses[$agentKey] = $agentClass;
        $this->agentRequestFactoryClasses[$agentKey] = $agentRequestFactoryClass;
        return $this;
    }

    public function has(string $agentKey): bool
    {
        return array_key_exists($agentKey, $this->agentClasses);
    }

    public function getAgent(string $agentKey): AgentInterface
    {
        if (!$this->has($agentKey)) {
            throw new \InvalidArgumentException("No agent registered under key: " . $agentKey);
        }
        return $this->agents->get($this->agentClasses[$agentKey]);
    }

    public function getAgentForRequest(AgentRequestInterface $request): AgentInterface
    {
        $requestClass = get_class($request);
        if (!isset($this->agentRequestClasses[$requestClass])) {
            throw new \InvalidArgumentException("No agent registered for request class: " . $requestClass);
        }
        $agentKey = $this->agentRequestClasses[$requestClass];
        return $this->getAgent($agentKey);
    }

    public function getRequestFactory(string $agentKey): AgentRequestFactoryInterface
    {
        if (!$this->has($agentKey)) {
            throw new \InvalidArgumentException("No agent registered under key: " . $agentKey);
        }
        return $this->agentRequestFactories->get($this->agentRequestFactoryClasses[$agentKey]);
    }
}
