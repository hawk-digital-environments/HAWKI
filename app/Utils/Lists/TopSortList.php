<?php
declare(strict_types=1);


namespace App\Utils\Lists;


use Illuminate\Support\Collection;
use Traversable;

/**
 * A key-value map with declarative topological ordering constraints.
 *
 * Items are keyed by a unique string that also serves as the identity for before/after constraints.
 * Iteration and {@see toArray()} yield values in their resolved topological order; the string keys
 * are not preserved in the output (use {@see get()} for key-based retrieval).
 *
 * Usage:
 * ```php
 * $pipeline = new TopSortList();
 * $pipeline->add(AuthEnricher::class, new AuthEnricher());
 * $pipeline->add(CacheEnricher::class, new CacheEnricher());
 * $pipeline->add(LogEnricher::class, new LogEnricher(), afterKeys: CacheEnricher::class);
 *
 * foreach ($pipeline as $enricher) { // AuthEnricher, CacheEnricher, LogEnricher
 *     $enricher->enrich($model);
 * }
 * ```
 *
 * @template TKey of string
 * @template TValue
 * @implements \IteratorAggregate<TKey, TValue>
 */
class TopSortList implements \IteratorAggregate, \Countable
{
    /**
     * @var array<TKey, TValue>
     */
    private array $items = [];
    /**
     * @var TopSortStringList<TKey>
     */
    private TopSortStringList $keySorter;

    public function __construct()
    {
        $this->keySorter = new TopSortStringList();
    }

    /**
     * Adds or replaces an item and optionally registers positioning constraints on its key.
     *
     * Calling add() with an existing key replaces its value and accumulates new constraints
     * without removing previously registered ones.
     *
     * @param string|string[]|null $beforeKeys Keys that this item's key must appear before.
     * @param string|string[]|null $afterKeys  Keys that this item's key must appear after.
     */
    public function add(
        string            $key,
        mixed             $value,
        array|string|null $beforeKeys = null,
        array|string|null $afterKeys = null
    ): self
    {
        $this->items[$key] = $value;
        $this->keySorter->add($key, $beforeKeys, $afterKeys);
        return $this;
    }

    /**
     * Removes an item and all its associated ordering constraints by key.
     */
    public function remove(string $key): self
    {
        unset($this->items[$key]);
        $this->keySorter->remove($key);
        return $this;
    }

    /**
     * Returns the value for a given key, or null when the key does not exist.
     */
    public function get(string $key): mixed
    {
        return $this->items[$key] ?? null;
    }

    /**
     * Returns all values in their resolved topological order.
     * String keys are not preserved; the returned array uses sequential integer keys.
     */
    public function toArray(): array
    {
        $sortedKeys = $this->keySorter->toArray();
        return array_map(fn($key) => $this->items[$key], $sortedKeys);
    }

    public function toCollection(): Collection
    {
        return collect($this->toArray());
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->keySorter->count();
    }
}
