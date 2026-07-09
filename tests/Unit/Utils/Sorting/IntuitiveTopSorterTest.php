<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Sorting;

use App\Utils\Sorting\Exceptions\CyclicDependencyException;
use App\Utils\Sorting\IntuitiveTopSorter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IntuitiveTopSorter::class)]
class IntuitiveTopSorterTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);

        static::assertInstanceOf(IntuitiveTopSorter::class, $sut);
    }

    // =========================================================================

    public function testItSortReturnsEmptyListWhenGivenEmptyList(): void
    {
        $sut = new IntuitiveTopSorter([]);

        static::assertSame([], $sut->sort());
    }

    public function testItSortReturnsListUnchangedWhenNoRulesAreRegistered(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItSortReturnsSingleItemListUnchanged(): void
    {
        $sut = new IntuitiveTopSorter(['a']);

        static::assertSame(['a'], $sut->sort());
    }

    // =========================================================================

    public function testItMoveItemAfterMovesItemAfterPivot(): void
    {
        // 'a' moves after 'c'; 'c' stays put → ['b', 'c', 'a']
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemAfter('a', 'c');

        static::assertSame(['b', 'c', 'a'], $sut->sort());
    }

    public function testItMoveItemAfterDoesNotDisplacePivot(): void
    {
        // 'b' moves after 'c'; 'c' stays in original position → ['a', 'c', 'b']
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemAfter('b', 'c');

        static::assertSame(['a', 'c', 'b'], $sut->sort());
    }

    public function testItMoveItemAfterHandlesItemAlreadyAfterPivot(): void
    {
        // 'c' is already after 'a'; no change expected
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemAfter('c', 'a');

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemAfterIgnoresUnknownItem(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemAfter('x', 'a');

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemAfterIgnoresUnknownPivot(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemAfter('a', 'x');

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemAfterAcceptsArrayOfPivots(): void
    {
        // 'a' must appear after both 'c' and 'd'
        $sut = new IntuitiveTopSorter(['a', 'b', 'c', 'd']);
        $sut->moveItemAfter('a', ['c', 'd']);

        static::assertSame(['b', 'c', 'd', 'a'], $sut->sort());
    }

    public function testItMoveItemAfterReturnsSelf(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b']);

        static::assertSame($sut, $sut->moveItemAfter('a', 'b'));
    }

    // =========================================================================

    public function testItMoveItemBeforeMovesItemBeforePivot(): void
    {
        // 'c' moves before 'a'; 'a' stays put → ['c', 'a', 'b']
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemBefore('c', 'a');

        static::assertSame(['c', 'a', 'b'], $sut->sort());
    }

    public function testItMoveItemBeforeDoesNotDisplacePivot(): void
    {
        // 'c' moves before 'b'; 'b' stays in original position → ['a', 'c', 'b']
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemBefore('c', 'b');

        static::assertSame(['a', 'c', 'b'], $sut->sort());
    }

    public function testItMoveItemBeforeHandlesItemAlreadyBeforePivot(): void
    {
        // 'a' is already before 'c'; no change expected
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemBefore('a', 'c');

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemBeforeIgnoresUnknownItem(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemBefore('x', 'a');

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemBeforeIgnoresUnknownPivot(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b', 'c']);
        $sut->moveItemBefore('a', 'x');

        static::assertSame(['a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemBeforeAcceptsArrayOfPivots(): void
    {
        // 'd' must appear before both 'a' and 'b'; 'a' and 'b' stay put
        $sut = new IntuitiveTopSorter(['a', 'b', 'c', 'd']);
        $sut->moveItemBefore('d', ['a', 'b']);

        static::assertSame(['d', 'a', 'b', 'c'], $sut->sort());
    }

    public function testItMoveItemBeforeReturnsSelf(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b']);

        static::assertSame($sut, $sut->moveItemBefore('b', 'a'));
    }

    // =========================================================================

    public function testItSortHandlesMultipleConstraints(): void
    {
        // foo moves after baz, qux moves before bar → ['qux', 'bar', 'baz', 'foo']
        $sut = new IntuitiveTopSorter(['foo', 'bar', 'baz', 'qux']);
        $sut->moveItemAfter('foo', 'baz');
        $sut->moveItemBefore('qux', 'bar');

        static::assertSame(['qux', 'bar', 'baz', 'foo'], $sut->sort());
    }

    public function testItSortPreservesRelativeOrderOfUnconstrained(): void
    {
        // Only 'a' has a constraint; 'b' and 'c' should keep their original relative order
        $sut = new IntuitiveTopSorter(['a', 'b', 'c', 'd']);
        $sut->moveItemAfter('a', 'd');

        static::assertSame(['b', 'c', 'd', 'a'], $sut->sort());
    }

    public function testItSortThrowsOnCircularDependency(): void
    {
        $sut = new IntuitiveTopSorter(['a', 'b']);
        $sut->moveItemAfter('a', 'b');
        $sut->moveItemAfter('b', 'a');

        $this->expectException(CyclicDependencyException::class);
        $this->expectExceptionMessage('Found a cyclic dependency in: b -> a -> b');

        $sut->sort();
    }
}
