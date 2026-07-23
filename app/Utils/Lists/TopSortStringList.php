<?php
declare(strict_types=1);


namespace App\Utils\Lists;


use App\Utils\Sorting\IntuitiveTopSorter;
use Illuminate\Support\Collection;
use Traversable;

/**
 * An ordered list of unique strings with declarative before/after positioning constraints.
 *
 * Items are stored in insertion order and sorted lazily on first access. Constraints can be
 * registered at add-time or accumulated via repeated calls to {@see add()}.
 *
 * Usage:
 * ```php
 * $list = new TopSortStringList();
 * $list->add('middleware.auth');
 * $list->add('middleware.logging');
 * $list->add('middleware.cors', before: 'middleware.auth'); // cors always runs before auth
 *
 * foreach ($list as $name) { // ['middleware.cors', 'middleware.auth', 'middleware.logging']
 *     $pipeline->pipe($name);
 * }
 * ```
 *
 * @template TItem of string
 * @implements \IteratorAggregate<TItem>
 */
class TopSortStringList implements \IteratorAggregate, \Countable
{
    private bool $isSorted = false;
    /**
     * @var array<TItem>
     */
    private array $items = [];
    /**
     * @var array<TItem, array<TItem>>
     */
    private array $rulesBefore = [];
    /**
     * @var array<TItem, array<TItem>>
     */
    private array $rulesAfter = [];

    /**
     * Adds an item to the list and optionally registers positioning constraints.
     *
     * Calling add() on an already-registered item is safe and simply accumulates
     * additional constraints without duplicating the entry.
     *
     * @param string|string[]|null $before Items that $item must appear before (those items stay; $item moves).
     * @param string|string[]|null $after  Items that $item must appear after (those items stay; $item moves).
     */
    public function add(string $item, array|string|null $before = null, array|string|null $after = null): self
    {
        if (!in_array($item, $this->items, true)) {
            $this->items[] = $item;
        }

        if ($before !== null) {
            foreach (collect($before) as $beforeItem) {
                $this->rulesBefore[$item][] = $beforeItem;
            }
        }

        if ($after !== null) {
            foreach (collect($after) as $afterItem) {
                $this->rulesAfter[$item][] = $afterItem;
            }
        }

        $this->isSorted = false;
        return $this;
    }

    /**
     * Removes an item and all its associated ordering constraints from the list.
     */
    public function remove(string $item): self
    {
        $this->items = array_values(array_filter($this->items, static fn($i) => $i !== $item));
        unset($this->rulesBefore[$item], $this->rulesAfter[$item]);
        $this->isSorted = false;
        return $this;
    }

    /**
     * Returns all items in their resolved topological order.
     * Items with no constraints preserve their insertion order relative to one another.
     */
    public function toArray(): array
    {
        $this->sort();
        return $this->items;
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
        return count($this->items);
    }

    private function sort(): void
    {
        if ($this->isSorted) {
            return;
        }

        $sorter = new IntuitiveTopSorter($this->items);
        foreach ($this->rulesBefore as $item => $beforeItems) {
            $sorter->moveItemBefore($item, $beforeItems);
        }
        foreach ($this->rulesAfter as $item => $afterItems) {
            $sorter->moveItemAfter($item, $afterItems);
        }

        $this->items = $sorter->sort();
        $this->isSorted = true;
    }
}
