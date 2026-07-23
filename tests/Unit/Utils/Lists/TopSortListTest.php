<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Lists;

use App\Utils\Lists\TopSortList;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TopSortList::class)]
class TopSortListTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new TopSortList();

        static::assertInstanceOf(TopSortList::class, $sut);
    }

    // =========================================================================

    public function testItAddAndGetStoreValueByKey(): void
    {
        $sut = new TopSortList();
        $sut->add('my.key', 'my-value');

        static::assertSame('my-value', $sut->get('my.key'));
    }

    public function testItAddReplacesValueForExistingKey(): void
    {
        $sut = new TopSortList();
        $sut->add('key', 'first');
        $sut->add('key', 'second');

        static::assertSame('second', $sut->get('key'));
    }

    public function testItAddAcceptsAnyValueType(): void
    {
        $obj = new \stdClass();
        $sut = new TopSortList();
        $sut->add('obj', $obj);
        $sut->add('arr', [1, 2, 3]);
        $sut->add('null', null);

        static::assertSame($obj, $sut->get('obj'));
        static::assertSame([1, 2, 3], $sut->get('arr'));
        static::assertNull($sut->get('null'));
    }

    public function testItAddReturnsSelf(): void
    {
        $sut = new TopSortList();

        static::assertSame($sut, $sut->add('a', 'value'));
    }

    // =========================================================================

    public function testItGetReturnsNullForMissingKey(): void
    {
        $sut = new TopSortList();

        static::assertNull($sut->get('non-existent'));
    }

    // =========================================================================

    public function testItRemoveDeletesItem(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB');
        $sut->remove('a');

        static::assertNull($sut->get('a'));
    }

    public function testItRemoveDecrementsCount(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB');
        $sut->remove('a');

        static::assertSame(1, $sut->count());
    }

    public function testItRemoveDoesNothingForUnknownKey(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'valueA');
        $sut->remove('non-existent');

        static::assertSame(1, $sut->count());
    }

    public function testItRemoveReturnsSelf(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'v');

        static::assertSame($sut, $sut->remove('a'));
    }

    // =========================================================================

    public function testItToArrayReturnsEmptyWhenNothingAdded(): void
    {
        $sut = new TopSortList();

        static::assertSame([], $sut->toArray());
    }

    public function testItToArrayReturnsValuesInInsertionOrderWhenNoConstraints(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB')->add('c', 'valueC');

        static::assertSame(['valueA', 'valueB', 'valueC'], $sut->toArray());
    }

    public function testItToArrayReturnsValuesSortedByAfterConstraint(): void
    {
        // 'a' moves after 'c'; 'c' stays → values in order: B, C, A
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB')->add('c', 'valueC');
        $sut->add('a', 'valueA', afterKeys: 'c');

        static::assertSame(['valueB', 'valueC', 'valueA'], $sut->toArray());
    }

    public function testItToArrayReturnsValuesSortedByBeforeConstraint(): void
    {
        // 'c' moves before 'a'; 'a' stays → values in order: C, A, B
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB')->add('c', 'valueC', beforeKeys: 'a');

        static::assertSame(['valueC', 'valueA', 'valueB'], $sut->toArray());
    }

    public function testItToArrayDropsStringKeysAndUsesSequentialIntegerKeys(): void
    {
        $sut = new TopSortList();
        $sut->add('foo', 'valueA')->add('bar', 'valueB');

        $result = $sut->toArray();

        static::assertArrayHasKey(0, $result);
        static::assertArrayHasKey(1, $result);
        static::assertArrayNotHasKey('foo', $result);
        static::assertArrayNotHasKey('bar', $result);
    }

    // =========================================================================

    public function testItCountReturnsZeroWhenEmpty(): void
    {
        $sut = new TopSortList();

        static::assertSame(0, $sut->count());
    }

    public function testItCountReturnsNumberOfEntries(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'v1')->add('b', 'v2')->add('c', 'v3');

        static::assertSame(3, $sut->count());
    }

    public function testItCountDoesNotIncrementOnDuplicateKey(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'first');
        $sut->add('a', 'second');

        static::assertSame(1, $sut->count());
    }

    // =========================================================================

    public function testItToCollectionReturnsCollectionInSortedOrder(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB')->add('c', 'valueC');
        $sut->add('a', 'valueA', afterKeys: 'c');

        static::assertSame(['valueB', 'valueC', 'valueA'], $sut->toCollection()->all());
    }

    // =========================================================================

    public function testItGetIteratorYieldsValuesInSortedOrder(): void
    {
        $sut = new TopSortList();
        $sut->add('a', 'valueA')->add('b', 'valueB')->add('c', 'valueC');
        $sut->add('a', 'valueA', afterKeys: 'c');

        static::assertSame(['valueB', 'valueC', 'valueA'], iterator_to_array($sut->getIterator()));
    }

    public function testItGetIteratorReturnsEmptyWhenNothingAdded(): void
    {
        $sut = new TopSortList();

        static::assertSame([], iterator_to_array($sut->getIterator()));
    }
}
