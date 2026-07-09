<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Lists;

use App\Utils\Lists\Exceptions\InvalidKeyGeneratorResultException;
use App\Utils\Lists\LazySingletonList;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LazySingletonList::class)]
#[CoversClass(InvalidKeyGeneratorResultException::class)]
class LazySingletonListTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        static::assertInstanceOf(LazySingletonList::class, $sut);
    }

    // =========================================================================

    public function testItGetCreatesInstanceOnFirstAccess(): void
    {
        $callCount = 0;
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            function (string $key) use (&$callCount): \stdClass {
                $callCount++;
                return new \stdClass();
            }
        );

        $sut->get('foo');

        static::assertSame(1, $callCount);
    }

    public function testItGetReturnsSameInstanceOnSubsequentAccess(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $first = $sut->get('foo');
        $second = $sut->get('foo');

        static::assertSame($first, $second);
    }

    public function testItGetCallsFactoryOnlyOncePerKey(): void
    {
        $callCount = 0;
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            function (string $key) use (&$callCount): \stdClass {
                $callCount++;
                return new \stdClass();
            }
        );

        $sut->get('foo');
        $sut->get('foo');
        $sut->get('foo');

        static::assertSame(1, $callCount);
    }

    public function testItGetReturnsDifferentInstancesForDifferentKeys(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $a = $sut->get('foo');
        $b = $sut->get('bar');

        static::assertNotSame($a, $b);
    }

    public function testItGetPassesOriginalKeyToFactory(): void
    {
        $receivedKey = null;
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            function (string $key) use (&$receivedKey): \stdClass {
                $receivedKey = $key;
                return new \stdClass();
            }
        );

        $sut->get('my-key');

        static::assertSame('my-key', $receivedKey);
    }

    public function testItGetPassesOriginalComplexKeyToFactoryNotTheStringKey(): void
    {
        // Ensures the factory receives the original input (e.g. an object),
        // not the string key produced by keyGenerator.
        $input = new \stdClass();
        $input->id = 42;

        $receivedKey = null;
        $sut = new LazySingletonList(
            fn(\stdClass $obj) => 'prefix_' . $obj->id,
            function (\stdClass $obj) use (&$receivedKey): string {
                $receivedKey = $obj;
                return 'instance';
            }
        );

        $sut->get($input);

        static::assertSame($input, $receivedKey);
    }

    // =========================================================================

    public function testItHasReturnsFalseBeforeFirstGet(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        static::assertFalse($sut->has('foo'));
    }

    public function testItHasReturnsTrueAfterGet(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $sut->get('foo');

        static::assertTrue($sut->has('foo'));
    }

    public function testItHasReturnsFalseForKeyNotYetRequested(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $sut->get('foo');

        static::assertFalse($sut->has('bar'));
    }

    // =========================================================================

    public function testItRemoveDeletesExistingInstance(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $sut->get('foo');
        $sut->remove('foo');

        static::assertFalse($sut->has('foo'));
    }

    public function testItRemoveCausesNextGetToCreateFreshInstance(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $first = $sut->get('foo');
        $sut->remove('foo');
        $second = $sut->get('foo');

        static::assertNotSame($first, $second);
    }

    public function testItRemoveDoesNothingForNonExistentKey(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        // Must not throw.
        $sut->remove('never-added');
        static::assertFalse($sut->has('never-added'));
    }

    // =========================================================================

    public function testItCountReturnsZeroWhenEmpty(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        static::assertSame(0, $sut->count());
    }

    public function testItCountReturnsNumberOfCreatedInstances(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $sut->get('a');
        $sut->get('b');
        $sut->get('c');

        static::assertSame(3, $sut->count());
    }

    public function testItCountDecrementsAfterRemove(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $sut->get('a');
        $sut->get('b');
        $sut->remove('a');

        static::assertSame(1, $sut->count());
    }

    public function testItCountDoesNotIncrementForRepeatedGetOnSameKey(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $sut->get('foo');
        $sut->get('foo');

        static::assertSame(1, $sut->count());
    }

    // =========================================================================

    public function testItGetIteratorIteratesOverCreatedInstances(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => $key . '_instance',
        );

        $sut->get('a');
        $sut->get('b');

        $result = iterator_to_array($sut->getIterator());

        static::assertSame(['a' => 'a_instance', 'b' => 'b_instance'], $result);
    }

    public function testItGetIteratorReturnsEmptyTraversableWhenNothingCreated(): void
    {
        $sut = new LazySingletonList(
            fn(string $key) => $key,
            fn(string $key) => new \stdClass(),
        );

        $result = iterator_to_array($sut->getIterator());

        static::assertSame([], $result);
    }

    // =========================================================================

    public function testItGetThrowsWhenKeyGeneratorReturnsNonString(): void
    {
        $sut = new LazySingletonList(
            fn(mixed $key) => 123, // intentionally returns int
            fn(mixed $key) => new \stdClass(),
        );

        $this->expectException(InvalidKeyGeneratorResultException::class);
        $this->expectExceptionMessage('Key generator must return a string.');

        $sut->get('foo');
    }

    public function testItHasThrowsWhenKeyGeneratorReturnsNonString(): void
    {
        $sut = new LazySingletonList(
            fn(mixed $key) => null, // intentionally returns null
            fn(mixed $key) => new \stdClass(),
        );

        $this->expectException(InvalidKeyGeneratorResultException::class);
        $this->expectExceptionMessage('Key generator must return a string.');

        $sut->has('foo');
    }

    public function testItRemoveThrowsWhenKeyGeneratorReturnsNonString(): void
    {
        $sut = new LazySingletonList(
            fn(mixed $key) => [], // intentionally returns array
            fn(mixed $key) => new \stdClass(),
        );

        $this->expectException(InvalidKeyGeneratorResultException::class);
        $this->expectExceptionMessage('Key generator must return a string.');

        $sut->remove('foo');
    }
}
