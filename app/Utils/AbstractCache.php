<?php
declare(strict_types=1);


namespace App\Utils;


use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Throwable;

/**
 * An abstract cache class that scopes cache keys to the class using it.
 * This prevents key collisions between different classes using the same cache repository.
 *
 * Why would I use this instead of just prefixing my keys manually?
 * 1. Automatic scoping: You don't have to remember to prefix your keys, it's done automatically.
 * 2. Consistency: All keys are scoped the same way, reducing the chance of human error.
 * 3. Easy to change scope: You can override the getBaseKey method to change the scope for a specific class.
 * 4. Cleaner code: Your cache operations are cleaner and more readable without manual key manipulation.
 *
 * Also, there are the following benefits:
 * 1. Clean dependency injection: You can inject a concrete cache class into your services without worrying about key collisions.
 * 2. Encapsulation: The caching logic is encapsulated within the class, making it easier to manage and maintain.
 * 3. Reusability: You can create multiple cache classes for different purposes without worrying about key collisions.
 * 4. Extendability: You can easily extend the abstract class to add more functionality or modify existing behavior for your specific use case.
 *
 * Additionally, this class provides "rememberIfNotNull" and "rememberForeverIfNotNull" methods,
 * which behave like the standard "remember" methods but do not cache null values.
 */
abstract class AbstractCache implements Repository
{
    protected Repository $repository;

    /**
     * Executed automatically when the class is resolved from the container.
     * @param Repository $repository
     * @return void
     */
    final public function setRepository(Repository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get($this->getScopedKey($key), $default);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return $this->repository->put($this->getScopedKey($key), $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return $this->repository->delete($this->getScopedKey($key));
    }

    /**
     * @inheritDoc
     */
    public function clear(bool $force = false): bool
    {
        if (!$force) {
            throw new \RuntimeException(sprintf(
                'To execute the %s::clear method, you must set the $force parameter to true to confirm the action. You have to do this, because the "clear" method will clear the entire cache, not just the scoped keys for this class.',
                static::class
            ));
        }

        return $this->repository->clear();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->repository->getMultiple($this->getScopedKeyList($keys), $default);
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return $this->repository->setMultiple($this->getScopedKeyValueList($values), $ttl);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->repository->deleteMultiple($this->getScopedKeyList($keys));
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->repository->has($this->getScopedKey($key));
    }

    /**
     * @inheritDoc
     */
    public function pull($key, $default = null): mixed
    {
        return $this->repository->pull($this->getScopedKey($key), $default);
    }

    /**
     * @inheritDoc
     */
    public function put($key, $value, $ttl = null): bool
    {
        return $this->repository->put($this->getScopedKey($key), $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function add($key, $value, $ttl = null): bool
    {
        return $this->repository->add($this->getScopedKey($key), $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $value = 1): bool|int
    {
        return $this->repository->increment($this->getScopedKey($key), $value);
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $value = 1): bool|int
    {
        return $this->repository->decrement($this->getScopedKey($key), $value);
    }

    /**
     * @inheritDoc
     */
    public function forever($key, $value): bool
    {
        return $this->repository->forever($this->getScopedKey($key), $value);
    }

    /**
     * @inheritDoc
     */
    public function remember($key, $ttl, Closure $callback): mixed
    {
        return $this->repository->remember($this->getScopedKey($key), $ttl, $callback);
    }

    /**
     * Behaves exactly like {@see remember}, but if the returned value from the callback is null,
     * it will not be cached, and the $defaultValue will be returned instead.
     *
     * @param string $key
     * @param \DateInterval|int|null $ttl
     * @param Closure $callback
     * @param mixed|null $defaultValue
     * @return mixed
     */
    public function rememberIfNotNull(
        string                 $key,
        \DateInterval|int|null $ttl,
        Closure                $callback,
        mixed                  $defaultValue = null
    ): mixed
    {
        return $this->handleNotNullCaching(
            fn($cb) => $this->repository->remember($this->getScopedKey($key), $ttl, $cb),
            $callback,
            $defaultValue
        );
    }

    /**
     * @inheritDoc
     */
    public function sear($key, Closure $callback): mixed
    {
        return $this->repository->sear($this->getScopedKey($key), $callback);
    }

    /**
     * @inheritDoc
     */
    public function rememberForever($key, Closure $callback): mixed
    {
        return $this->repository->rememberForever($this->getScopedKey($key), $callback);
    }

    /**
     * Behaves exactly like {@see rememberForever}, but if the returned value from the callback is null,
     * it will not be cached, and the $defaultValue will be returned instead.
     *
     * @param string $key
     * @param Closure $callback
     * @param mixed|null $defaultValue
     * @return mixed
     */
    public function rememberForeverIfNotNull(
        string  $key,
        Closure $callback,
        mixed   $defaultValue = null
    ): mixed
    {
        return $this->handleNotNullCaching(
            fn($cb) => $this->repository->rememberForever($this->getScopedKey($key), $cb),
            $callback,
            $defaultValue
        );
    }

    /**
     * @inheritDoc
     */
    public function forget($key): bool
    {
        return $this->repository->forget($this->getScopedKey($key));
    }

    /**
     * @inheritDoc
     */
    public function getStore(): Store
    {
        return $this->repository->getStore();
    }

    /**
     * Returns the base key used for scoping cache keys.
     * @return string The base key used for scoping cache keys. By default, it's the class name.
     * @api Can be overridden to provide a custom base key for scoping cache keys.
     */
    protected function getBaseKey(): string
    {
        return static::class;
    }

    /**
     * Returns the scoped cache key for the given key.
     * The scoped key is generated by hashing the base key and the given key.
     * This allows even large keys to be used without exceeding cache key length limits.
     * @param string $key
     * @return string
     */
    final protected function getScopedKey(string $key): string
    {
        return hash('sha256', $this->getBaseKey() . ':' . $key);
    }

    /**
     * Returns an iterable of scoped cache keys for the given iterable of keys.
     * @param iterable $keys A list of cache keys as an array or Traversable.
     * @return iterable An iterable of scoped cache keys.
     */
    final protected function getScopedKeyList(iterable $keys): iterable
    {
        foreach ($keys as $key) {
            yield $this->getScopedKey($key);
        }
    }

    /**
     * Returns an iterable of scoped cache key-value pairs for the given iterable of key-value pairs.
     * @param iterable $keyValuePairs An associative array or Traversable of cache key-value pairs.
     * @return iterable An iterable of scoped cache key-value pairs.
     */
    final protected function getScopedKeyValueList(iterable $keyValuePairs): iterable
    {
        foreach ($keyValuePairs as $key => $value) {
            yield $this->getScopedKey($key) => $value;
        }
    }

    /**
     * Internal helper for rememberIfNotNull and similar methods.
     * Usage:
     *
     * ```php
     * $this->handleNotNullCaching(
     *     fn($cb) => $this->repository->remember($key, $ttl, $cb),
     *     $callback,
     *     $defaultValue
     * );
     * ```
     * @param Closure $handler A closure in which the real caching method is called. The sole parameter is a closure that should be called to get the value to cache.
     * @param Closure $callback The original callback to get the value.
     * @param mixed $defaultValue The default value to return if the callback returns null.
     * @return mixed The cached value or the default value.
     * @throws Throwable
     */
    private function handleNotNullCaching(\Closure $handler, \Closure $callback, mixed $defaultValue): mixed
    {
        try {
            $errorCode = 'EXIT_CODE_' . random_bytes(16);
            return $handler(static function () use ($callback, $errorCode) {
                $result = $callback();
                if ($result === null) {
                    throw new \RuntimeException($errorCode);
                }
                return $result;
            });
        } catch (\Throwable $e) {
            if ($e->getMessage() === $errorCode) {
                return $defaultValue;
            }
            throw $e;
        }
    }
}
