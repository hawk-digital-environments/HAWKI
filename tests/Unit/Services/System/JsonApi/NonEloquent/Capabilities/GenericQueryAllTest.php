<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\NonEloquent\Capabilities;

use App\Services\System\JsonApi\NonEloquent\Capabilities\GenericQueryAll;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericQueryAll::class)]
class GenericQueryAllTest extends TestCase
{
    public function testItConstructsWithIterable(): void
    {
        $sut = new GenericQueryAll([]);

        static::assertInstanceOf(GenericQueryAll::class, $sut);
    }

    public function testItConstructsWithClosure(): void
    {
        $sut = new GenericQueryAll(fn() => []);

        static::assertInstanceOf(GenericQueryAll::class, $sut);
    }

    // =========================================================================
    // get() with iterable
    // =========================================================================

    public function testItReturnsIterableDirectly(): void
    {
        $items = ['a', 'b', 'c'];
        $sut = new GenericQueryAll($items);

        static::assertSame($items, $sut->get());
    }

    public function testItReturnsGeneratorDirectly(): void
    {
        $generator = (function () {
            yield 'first';
            yield 'second';
        })();

        $sut = new GenericQueryAll($generator);

        static::assertSame($generator, $sut->get());
    }

    // =========================================================================
    // get() with closure
    // =========================================================================

    public function testItInvokesClosureOnGet(): void
    {
        $items = ['x', 'y'];
        $called = 0;
        $sut = new GenericQueryAll(function () use ($items, &$called) {
            $called++;
            return $items;
        });

        $result = $sut->get();

        static::assertSame($items, $result);
        static::assertSame(1, $called);
    }

    public function testItDoesNotInvokeClosureBeforeGet(): void
    {
        $called = 0;
        new GenericQueryAll(function () use (&$called) {
            $called++;
            return [];
        });

        static::assertSame(0, $called);
    }

    public function testItInvokesClosureOnEachCall(): void
    {
        $callCount = 0;
        $sut = new GenericQueryAll(function () use (&$callCount) {
            $callCount++;
            return [];
        });

        $sut->get();
        $sut->get();

        static::assertSame(2, $callCount);
    }
}
