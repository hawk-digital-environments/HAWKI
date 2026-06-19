<?php
declare(strict_types=1);


namespace App\Services\System\Container;

use App\Services\System\Container\Exceptions\ServiceLocatorException;
use Illuminate\Container\Container;

/**
 * A lightweight service container with local overrides and callback-execution injection.
 *
 * **Service resolution** (`set`/`get`): services are stored locally first. When a service is not found
 * locally, resolution falls back to the injected {@see Container} (if any).
 *
 * **Callback resolution** (`setCallParams`/`call`): callbacks are executed via the container unless
 * pre-registered params are present for the given execution ID. The execution ID doubles as an
 * injection point for tests — override any callback result without touching the global container.
 *
 * This is the backing implementation for {@see ServiceLocatorTrait}. It can also be injected directly
 * when the `call()` mechanism is needed alongside standard service-locator functionality.
 *
 * @see ServiceLocatorTrait for the typical consumer API (e.g. in API Resources).
 */
class ServiceLocator
{
    private array $executionParams = [];
    private array $services = [];

    public function __construct(
        // Can be null for mocking in a testing environment
        private Container|null $container = null
    )
    {
    }

    /**
     * Replaces the container used for service and callback resolution.
     * Pass null to restrict resolution to locally registered services and pre-registered params only,
     * which is useful in tests to force explicit setup and catch missing mocks early.
     */
    public function setContainer(Container|null $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Registers a service under the given identifier.
     * A locally registered service always takes precedence over the container.
     */
    public function set(string $id, mixed $service): self
    {
        $this->services[$id] = $service;
        return $this;
    }

    /**
     * Resolves the service registered under the given identifier.
     *
     * Resolution order:
     *  1. Locally registered services (see {@see set()})
     *  2. The injected container (see {@see setContainer()})
     *
     * @throws ServiceLocatorException when the service is not found locally and no container is available.
     */
    public function get(string $id): mixed
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if ($this->container) {
            return $this->container->make($id);
        }

        throw ServiceLocatorException::becauseServiceNotFound($id);
    }

    /**
     * Pre-registers parameters to spread into a callback when {@see call()} is invoked with the same execution ID.
     * Pre-registered params bypass the container entirely — useful in tests to supply controlled values.
     *
     * @param string $executionId Execution identifier matching the one passed to {@see call()}.
     * @param array  $params      Parameters spread into the callback as positional arguments.
     */
    public function setCallParams(string $executionId, array $params): self
    {
        $this->executionParams[$executionId] = $params;
        return $this;
    }

    /**
     * Executes a callback, resolving its dependencies when needed.
     *
     * Resolution order:
     *  1. Pre-registered params for the given execution ID (see {@see setCallParams()}) — spread as positional args.
     *  2. The injected container (see {@see setContainer()}) — resolved via {@see Container::call()}.
     *
     * @param string|array $executionId  Callback identifier; arrays are joined with '.' so callers can build
     *                                   dot-paths by passing segments (e.g. ['scope', 'apply', $key]).
     * @param array|null   $parameters   Named parameters forwarded to {@see Container::call()} when falling back
     *                                   to the container. Has no effect when pre-registered params are present.
     * @throws ServiceLocatorException when no pre-registered params and no container are available.
     */
    public function call(string|array $executionId, callable $callback, array|null $parameters = null): mixed
    {
        if (is_array($executionId)) {
            $executionId = implode('.', $executionId);
        }

        $definedParams = $this->executionParams[$executionId] ?? null;
        if ($definedParams) {
            return $callback(...$definedParams);
        }

        if (!$this->container) {
            throw ServiceLocatorException::becauseCallbackHasNoContainer($executionId);
        }

        return $this->container->call($callback, $parameters ?? []);
    }
}
