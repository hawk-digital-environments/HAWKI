<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Values;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use Illuminate\Support\Traits\ForwardsCalls;
use Laravel\Ai\Providers\Provider;

/** @mixin AiProvider */
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

    public function getRealProvider(): AiProvider
    {
        return $this->provider;
    }

    /**
     * Determine if an attribute exists on the provider.
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key)
    {
        return isset($this->provider->{$key});
    }

    /**
     * Unset an attribute on the provider.
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key)
    {
        unset($this->provider->{$key});
    }

    /**
     * Dynamically get properties from the underlying provider.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->provider->{$key};
    }

    public function __call($name, $arguments)
    {
        $this->forwardCallTo($this->provider, $name, $arguments);
    }
}
