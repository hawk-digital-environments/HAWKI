<?php
declare(strict_types=1);


namespace App\Services\System\JsonApi\NonEloquent\Capabilities;


use LaravelJsonApi\NonEloquent\Capabilities\QueryAll;

/**
 * Generic {@see QueryAll} implementation for non-Eloquent JSON:API resources.
 *
 * Accepts either a pre-built iterable or a callable that lazily produces one.
 * Use the callable form when the resource list should only be fetched when the
 * query is actually executed, rather than eagerly in a constructor.
 *
 * ```php
 * class ConfigRepository extends AbstractRepository
 * {
 *     public function queryAll(): QueryAll
 *     {
 *         return new GenericQueryAll(fn() => $this->configService->all());
 *     }
 * }
 * ```
 */
class GenericQueryAll extends QueryAll
{
    /**
     * @param iterable|\Closure $items Pre-built iterable or a callable returning one.
     *                                  Pass a closure to defer fetching until the
     *                                  query is actually executed.
     */
    public function __construct(
        private readonly iterable|\Closure $items
    )
    {
        parent::__construct();
    }

    /**
     * Returns all resources, invoking the closure if one was provided.
     */
    public function get(): iterable
    {
        if (is_callable($this->items)) {
            return ($this->items)();
        }
        return $this->items;
    }
}
