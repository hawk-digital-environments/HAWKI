<?php
declare(strict_types=1);

namespace App\Utils\Sorting;


use App\Utils\Sorting\Exceptions\CyclicDependencyException;

/**
 * Directed dependency graph used internally by {@see IntuitiveTopSorter}.
 *
 * The $order map encodes ordering constraints as dependencies:
 * `order[A] = [B, C]` means A must appear after both B and C in the sorted result
 * (A "depends on" B and C).
 */
class DependencyGraph
{
    protected array $order;

    protected array $dependencies = [];

    /**
     * @param array $order Map of item => items that must precede it (its dependencies).
     */
    public function __construct(array $order)
    {
        $this->order = $order;
    }

    /**
     * Returns true when B is listed as an immediate dependency of A,
     * meaning A must appear after B in the sorted result.
     */
    public function doesAHaveDirectDependencyOnB(string $a, string $b): bool
    {
        return in_array($b, $this->order[$a], true);
    }

    /**
     * Returns true when B appears anywhere in A's full transitive dependency chain.
     *
     * @throws CyclicDependencyException When a dependency cycle is detected during traversal.
     */
    public function doesAHaveTransitiveDependencyOnB(string $a, string $b): bool
    {
        return in_array($b, $this->resolveDependencies($a), true);
    }

    /**
     * Recursively collects all transitive dependencies for the given item.
     * Results are memoized after the first resolution.
     *
     * @param string[] $path Items visited on the current traversal path, used to detect cycles.
     * @throws CyclicDependencyException When $key is encountered again within $path.
     */
    protected function resolveDependencies(string $key, array $path = []): array
    {
        if (isset($this->dependencies[$key])) {
            return $this->dependencies[$key];
        }

        $dependencies = [[], []];

        foreach ($this->order[$key] ?? [] as $depKey) {
            if (in_array($depKey, $path, true)) {
                throw CyclicDependencyException::forLoopInPath($path, $depKey);
            }

            $dependencies[] = [$depKey];
            $dependencies[] = $this->resolveDependencies($depKey, array_merge($path, [$depKey]));
        }

        return $this->dependencies[$key] = array_unique(array_merge(...$dependencies));
    }
}
