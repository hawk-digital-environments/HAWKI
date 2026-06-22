<?php
declare(strict_types=1);


namespace App\Utils\Lists;


use App\Utils\Lists\Exceptions\InvalidKeyGeneratorResultException;
use Traversable;

/**
 * A keyed instance cache that creates each value on first access and reuses it on subsequent calls.
 *
 * Two closures drive the lifecycle:
 * - The `keyGenerator` maps any input to a unique string storage key, so complex inputs such as
 *   objects or arrays can be used as lookup identifiers without implementing any interface.
 * - The `factory` receives the original input and returns the instance to cache. It is called at
 *   most once per unique key for the lifetime of this list.
 *
 * Usage example:
 * ```php
 * $clients = new LazySingletonList(
 *     keyGenerator: fn(McpServer $server) => 'mcp_' . $server->id,
 *     factory:      fn(McpServer $server) => $factory->createForServer($server),
 * );
 *
 * $client = $clients->get($server);            // instance created on first call
 * $client === $clients->get($server);          // same instance returned on subsequent calls
 * $clients->has($server);                      // true
 * $clients->remove($server);                   // evicts; next get() creates a fresh instance
 * ```
 *
 * @template TKey
 * @template TValue
 */
class LazySingletonList implements \IteratorAggregate, \Countable
{
    private array $instances = [];

    public function __construct(
        /**
         * Converts the input key to a unique string storage key.
         * Must always return a string; non-string results throw {@see InvalidKeyGeneratorResultException}.
         *
         * @var \Closure(TKey): string
         */
        private readonly \Closure $keyGenerator,
        /**
         * Called once per unique key to create the cached instance.
         * Receives the original input, not the generated string key.
         *
         * @var \Closure(TKey): TValue
         */
        private readonly \Closure $factory
    )
    {
    }

    /**
     * Returns the cached instance for the given key, creating it via the factory on first access.
     *
     * @return TValue
     */
    public function get(mixed $key): mixed
    {
        $realKey = $this->getRealKey($key);
        if (!isset($this->instances[$realKey])) {
            $this->instances[$realKey] = ($this->factory)($key);
        }
        return $this->instances[$realKey];
    }

    /**
     * Returns true if an instance has already been created for the given key.
     */
    public function has(mixed $key): bool
    {
        return isset($this->instances[$this->getRealKey($key)]);
    }

    /**
     * Removes the cached instance for the given key.
     * The next {@see get()} call for this key will invoke the factory to create a fresh instance.
     */
    public function remove(mixed $key): void
    {
        unset($this->instances[$this->getRealKey($key)]);
    }

    private function getRealKey(mixed $key): string
    {
        $realKey = ($this->keyGenerator)($key);
        if (!is_string($realKey)) {
            throw InvalidKeyGeneratorResultException::forNonStringResult();
        }
        return $realKey;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->instances);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->instances);
    }
}
