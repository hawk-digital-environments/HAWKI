<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Sorting;

use App\Utils\Sorting\DependencyGraph;
use App\Utils\Sorting\Exceptions\CyclicDependencyException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DependencyGraph::class)]
class DependencyGraphTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new DependencyGraph(['a' => [], 'b' => []]);

        static::assertInstanceOf(DependencyGraph::class, $sut);
    }

    // =========================================================================

    public function testItDoesAHaveDirectDependencyOnBReturnsTrueWhenDependencyIsListed(): void
    {
        $sut = new DependencyGraph(['a' => ['b', 'c'], 'b' => [], 'c' => []]);

        static::assertTrue($sut->doesAHaveDirectDependencyOnB('a', 'b'));
        static::assertTrue($sut->doesAHaveDirectDependencyOnB('a', 'c'));
    }

    public function testItDoesAHaveDirectDependencyOnBReturnsFalseWhenNoDependency(): void
    {
        $sut = new DependencyGraph(['a' => ['b'], 'b' => [], 'c' => []]);

        static::assertFalse($sut->doesAHaveDirectDependencyOnB('a', 'c'));
        static::assertFalse($sut->doesAHaveDirectDependencyOnB('b', 'a'));
    }

    public function testItDoesAHaveDirectDependencyOnBReturnsFalseWhenDependencyListIsEmpty(): void
    {
        $sut = new DependencyGraph(['a' => [], 'b' => []]);

        static::assertFalse($sut->doesAHaveDirectDependencyOnB('a', 'b'));
    }

    // =========================================================================

    public function testItDoesAHaveTransitiveDependencyOnBReturnsTrueForDirectDependency(): void
    {
        $sut = new DependencyGraph(['a' => ['b'], 'b' => [], 'c' => []]);

        static::assertTrue($sut->doesAHaveTransitiveDependencyOnB('a', 'b'));
    }

    public function testItDoesAHaveTransitiveDependencyOnBReturnsTrueForTransitiveDependency(): void
    {
        // a -> b -> c: a transitively depends on c
        $sut = new DependencyGraph(['a' => ['b'], 'b' => ['c'], 'c' => []]);

        static::assertTrue($sut->doesAHaveTransitiveDependencyOnB('a', 'c'));
    }

    public function testItDoesAHaveTransitiveDependencyOnBReturnsTrueForDeepChain(): void
    {
        // a -> b -> c -> d: a transitively depends on d
        $sut = new DependencyGraph(['a' => ['b'], 'b' => ['c'], 'c' => ['d'], 'd' => []]);

        static::assertTrue($sut->doesAHaveTransitiveDependencyOnB('a', 'd'));
    }

    public function testItDoesAHaveTransitiveDependencyOnBReturnsFalseForUnrelatedItems(): void
    {
        $sut = new DependencyGraph(['a' => ['b'], 'b' => [], 'c' => []]);

        static::assertFalse($sut->doesAHaveTransitiveDependencyOnB('a', 'c'));
        static::assertFalse($sut->doesAHaveTransitiveDependencyOnB('b', 'a'));
    }

    public function testItDoesAHaveTransitiveDependencyOnBReturnsFalseWhenNoDependencies(): void
    {
        $sut = new DependencyGraph(['a' => [], 'b' => []]);

        static::assertFalse($sut->doesAHaveTransitiveDependencyOnB('a', 'b'));
    }

    // =========================================================================

    public function testItDoesAHaveTransitiveDependencyOnBThrowsOnCyclicDependency(): void
    {
        // a -> b -> a
        $sut = new DependencyGraph(['a' => ['b'], 'b' => ['a']]);

        $this->expectException(CyclicDependencyException::class);
        $this->expectExceptionMessage('Found a cyclic dependency in: b -> a -> b');

        $sut->doesAHaveTransitiveDependencyOnB('a', 'b');
    }

    public function testItDoesAHaveTransitiveDependencyOnBMemoizesResolvedDependencies(): void
    {
        // b -> c: resolving 'b' should cache ['c']; resolving 'a' builds on the cached result.
        $sut = new DependencyGraph(['a' => ['b'], 'b' => ['c'], 'c' => []]);

        // First call populates the cache for 'b' and 'a' transitively.
        $sut->doesAHaveTransitiveDependencyOnB('a', 'c');

        // Second call on 'b' directly should hit the memoized result, not re-traverse.
        static::assertTrue($sut->doesAHaveTransitiveDependencyOnB('b', 'c'));
    }
}
