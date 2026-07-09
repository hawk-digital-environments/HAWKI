<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Values;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use Illuminate\Support\Traits\ForwardsCalls;
use Laravel\Ai\Providers\Provider;

/**
 * A resolved, ready-to-use bundle that ties an {@see AiProvider} Eloquent model to its
 * instantiated adapter and driver.
 *
 * Callers that need the full provider context — model data AND the ability to invoke the
 * underlying AI driver — receive one of these from {@see \App\Services\Ai\Providers\AiProviderProxyResolver}.
 * The proxy transparently forwards property reads and method calls to the wrapped
 * {@see AiProvider} model, so existing code that expects an `AiProvider` instance can
 * consume an `AiProviderProxy` without modification.
 *
 * ```php
 * $proxy = $resolver->resolve('openAi');
 *
 * // Access Eloquent model attributes directly:
 * echo $proxy->name;          // forwarded to $provider->name
 * echo $proxy->api_url;       // forwarded to $provider->api_url
 *
 * // Use the adapter for business logic:
 * $models = $proxy->adapter->getModels($proxy);
 *
 * // Use the driver for AI calls:
 * $proxy->driver->chat(...);
 *
 * // Retrieve the underlying Eloquent model when needed:
 * $aiProvider = $proxy->getRealProvider();
 * ```
 *
 * @mixin AiProvider
 */
readonly class AiProviderProxy
{
    use ForwardsCalls;

    public function __construct(
        protected AiProvider            $provider,
        public ProviderAdapterInterface $adapter,
        public Provider                 $driver
    )
    {
    }

    /**
     * Returns the underlying {@see AiProvider} Eloquent model.
     *
     * Use this when you need the concrete model type — e.g. for repository calls or
     * policy checks — rather than the proxy surface.
     */
    public function getRealProvider(): AiProvider
    {
        return $this->provider;
    }

    /**
     * Checks whether an attribute exists on the underlying provider model.
     */
    public function __isset(string $key): bool
    {
        return isset($this->provider->{$key});
    }

    /**
     * Unsets an attribute on the underlying provider model.
     */
    public function __unset(string $key): void
    {
        unset($this->provider->{$key});
    }

    /**
     * Forwards property reads to the underlying provider model.
     */
    public function __get(string $key): mixed
    {
        return $this->provider->{$key};
    }

    /**
     * Forwards method calls to the underlying provider model.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->forwardCallTo($this->provider, $name, $arguments);
    }
}
