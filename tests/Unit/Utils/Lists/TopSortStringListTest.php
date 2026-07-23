<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Lists;

use App\Utils\Lists\TopSortStringList;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TopSortStringList::class)]
class TopSortStringListTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new TopSortStringList();

        static::assertInstanceOf(TopSortStringList::class, $sut);
    }

    // =========================================================================

    public function testItToArrayReturnsEmptyArrayWhenNothingAdded(): void
    {
        $sut = new TopSortStringList();

        static::assertSame([], $sut->toArray());
    }

    public function testItToArrayReturnsSingleItem(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a');

        static::assertSame(['a'], $sut->toArray());
    }

    public function testItToArrayPreservesInsertionOrderWhenNoConstraints(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');

        static::assertSame(['a', 'b', 'c'], $sut->toArray());
    }

    public function testItToArrayDeduplicatesItemsOnRepeatedAdd(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('a');

        static::assertSame(['a', 'b'], $sut->toArray());
    }

    // =========================================================================

    public function testItAddBeforeConstraintMovesItemBeforePivot(): void
    {
        // 'c' moves before 'a'; 'a' stays → ['c', 'a', 'b']
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c', before: 'a');

        static::assertSame(['c', 'a', 'b'], $sut->toArray());
    }

    public function testItAddAfterConstraintMovesItemAfterPivot(): void
    {
        // 'a' moves after 'c'; 'c' stays → ['b', 'c', 'a']
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');
        $sut->add('a', after: 'c');

        static::assertSame(['b', 'c', 'a'], $sut->toArray());
    }

    public function testItAddAcceptsArrayForBeforeConstraint(): void
    {
        // 'd' must come before both 'a' and 'b'
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c')->add('d', before: ['a', 'b']);

        static::assertSame(['d', 'a', 'b', 'c'], $sut->toArray());
    }

    public function testItAddAcceptsArrayForAfterConstraint(): void
    {
        // 'a' must come after both 'c' and 'd'
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c')->add('d');
        $sut->add('a', after: ['c', 'd']);

        static::assertSame(['b', 'c', 'd', 'a'], $sut->toArray());
    }

    public function testItAddAccumulatesConstraintsAcrossMultipleCalls(): void
    {
        // First call registers 'a', second call adds a before constraint
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');
        $sut->add('a', after: 'c'); // accumulate without duplicating 'a'

        static::assertSame(['b', 'c', 'a'], $sut->toArray());
    }

    public function testItAddReturnsSelf(): void
    {
        $sut = new TopSortStringList();

        static::assertSame($sut, $sut->add('a'));
    }

    // =========================================================================

    public function testItRemoveDeletesItem(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');
        $sut->remove('b');

        static::assertSame(['a', 'c'], $sut->toArray());
    }

    public function testItRemoveDropsConstraintsForRemovedItem(): void
    {
        // 'a' is added with a before constraint; after removal, 'b' and 'c' keep original order
        $sut = new TopSortStringList();
        $sut->add('a', before: 'b')->add('b')->add('c');
        $sut->remove('a');

        static::assertSame(['b', 'c'], $sut->toArray());
    }

    public function testItRemoveDoesNothingForUnknownItem(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b');
        $sut->remove('x');

        static::assertSame(['a', 'b'], $sut->toArray());
    }

    public function testItRemoveReturnsSelf(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a');

        static::assertSame($sut, $sut->remove('a'));
    }

    // =========================================================================

    public function testItCountReturnsZeroWhenEmpty(): void
    {
        $sut = new TopSortStringList();

        static::assertSame(0, $sut->count());
    }

    public function testItCountReturnsNumberOfItems(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');

        static::assertSame(3, $sut->count());
    }

    public function testItCountDoesNotCountDuplicates(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('a');

        static::assertSame(1, $sut->count());
    }

    public function testItCountDecrementsAfterRemove(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');
        $sut->remove('b');

        static::assertSame(2, $sut->count());
    }

    // =========================================================================

    public function testItToCollectionReturnsCollectionInSortedOrder(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');
        $sut->add('c', before: 'a');

        static::assertSame(['c', 'a', 'b'], $sut->toCollection()->all());
    }

    // =========================================================================

    public function testItGetIteratorYieldsItemsInSortedOrder(): void
    {
        $sut = new TopSortStringList();
        $sut->add('a')->add('b')->add('c');
        $sut->add('a', after: 'c');

        static::assertSame(['b', 'c', 'a'], iterator_to_array($sut->getIterator()));
    }

    public function testItGetIteratorReturnsEmptyWhenNothingAdded(): void
    {
        $sut = new TopSortStringList();

        static::assertSame([], iterator_to_array($sut->getIterator()));
    }
}
