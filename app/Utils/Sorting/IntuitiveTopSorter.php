<?php
declare(strict_types=1);

namespace App\Utils\Sorting;

/**
 * Topological sorter that keeps "anchor" (pivot) items stable while repositioning the dependent item.
 *
 * Standard topological sort algorithms move the pivot to satisfy a constraint, which is often
 * counterintuitive. This sorter inverts that: the pivot stays where it is and the dependent item
 * is the one that moves.
 *
 * Given ['a', 'b', 'c']:
 * - moveItemAfter('a', 'c')  → ['b', 'c', 'a']   (a moves after c;  c stays put)
 * - moveItemBefore('c', 'a') → ['c', 'a', 'b']   (c moves before a; a stays put)
 *
 * Usage:
 * ```php
 * $sorter = new IntuitiveTopSorter(['foo', 'bar', 'baz', 'qux']);
 * $sorter->moveItemAfter('foo', 'baz');   // foo moves after baz; baz stays
 * $sorter->moveItemBefore('qux', 'bar'); // qux moves before bar; bar stays
 * $result = $sorter->sort(); // ['qux', 'bar', 'baz', 'foo']
 * ```
 *
 * Circular constraints (e.g. A after B and B after A) cause
 * {@see \App\Utils\Sorting\Exceptions\CyclicDependencyException}.
 *
 * @see https://blog.gapotchenko.com/stable-topological-sort Inspired by Oleksiy Gapotchenko's
 *      work on stable topological sort algorithms.
 */
class IntuitiveTopSorter
{
    protected array $list = [];

    protected array $order = [];

    protected array $pivotItems = [];

    public function __construct(array $list)
    {
        $this->list = $list;
    }

    /**
     * Registers a constraint to place $item after $pivotItem in the sorted result.
     * The $pivotItem stays in its relative position; $item is repositioned.
     *
     * Both $item and $pivotItem must already exist in the list; unknown values are silently ignored.
     *
     * @param string|string[] $pivotItem One or more anchor items to place $item after.
     */
    public function moveItemAfter(string $item, string|array $pivotItem): self
    {
        if (is_array($pivotItem)) {
            foreach ($pivotItem as $pItem) {
                $this->moveItemAfter($item, $pItem);
            }
            return $this;
        }

        if (!in_array($item, $this->list, true) || !in_array($pivotItem, $this->list, true)) {
            return $this;
        }

        $this->pivotItems[] = $pivotItem;
        $this->order[$item][] = $pivotItem;

        return $this;
    }

    /**
     * Registers a constraint to place $item before $pivotItem in the sorted result.
     * The $pivotItem stays in its relative position; $item is repositioned.
     *
     * Both $item and $pivotItem must already exist in the list; unknown values are silently ignored.
     *
     * @param string|string[] $pivotItem One or more anchor items to place $item before.
     */
    public function moveItemBefore(string $item, string|array $pivotItem): self
    {
        if (is_array($pivotItem)) {
            foreach ($pivotItem as $pItem) {
                $this->moveItemBefore($item, $pItem);
            }
            return $this;
        }

        if (!in_array($item, $this->list, true) || !in_array($pivotItem, $this->list, true)) {
            return $this;
        }

        $this->pivotItems[] = $pivotItem;
        $this->order[$pivotItem][] = $item;

        return $this;
    }

    /**
     * Applies all registered ordering constraints and returns the sorted list.
     *
     * Items with no applicable constraints preserve their original relative order.
     *
     * @throws \App\Utils\Sorting\Exceptions\CyclicDependencyException When constraints form a cycle.
     */
    public function sort(): array
    {
        if (empty($this->order)) {
            return $this->list;
        }

        $order = array_fill_keys($this->list, []);
        $order = array_merge($order, $this->order);

        $graph = new DependencyGraph($order);

        $sorted = $this->list;
        $length = count($this->list);

        for ($h = 0; $h < $length; $h++) {
            for ($i = 0; $i < $length; $i++) {
                for ($j = 0; $j < $i; $j++) {
                    if (!$graph->doesAHaveDirectDependencyOnB($sorted[$j], $sorted[$i])) {
                        continue;
                    }

                    $jOnI = $graph->doesAHaveTransitiveDependencyOnB($sorted[$j], $sorted[$i]);
                    $iOnJ = $graph->doesAHaveTransitiveDependencyOnB($sorted[$i], $sorted[$j]);

                    if ($jOnI && $iOnJ) {
                        continue;
                    }

                    if (in_array($sorted[$j], $this->pivotItems, true)) {
                        $_j = $j;
                        $j = $i;
                        $i = $_j;
                        unset($_j);
                    }

                    $move = $sorted[$j];
                    unset($sorted[$j]);

                    array_splice($sorted, $i, 0, $move);
                    continue 3;
                }
            }

            break;
        }

        return $sorted;
    }
}
