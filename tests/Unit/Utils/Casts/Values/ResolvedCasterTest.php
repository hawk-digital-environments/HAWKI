<?php
declare(strict_types=1);


namespace Tests\Unit\Utils\Casts\Values;

use App\Utils\Casts\Values\ResolvedCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ResolvedCaster::class)]
class ResolvedCasterTest extends TestCase
{
    public function testItConstructs(): void
    {
        $sut = new ResolvedCaster(
            casterClass: 'App\\TestCaster',
            args: []
        );

        static::assertInstanceOf(ResolvedCaster::class, $sut);
    }

    public function testItStoresCasterClass(): void
    {
        $casterClass = 'App\\Utils\\Casts\\Casters\\DateCaster';
        $sut = new ResolvedCaster(
            casterClass: $casterClass,
            args: []
        );

        static::assertSame($casterClass, $sut->casterClass);
    }

    public function testItStoresArgs(): void
    {
        $args = ['format' => 'Y-m-d', 'timezone' => 'UTC'];
        $sut = new ResolvedCaster(
            casterClass: 'App\\TestCaster',
            args: $args
        );

        static::assertSame($args, $sut->args);
    }

    public function testItImplementsStringable(): void
    {
        $sut = new ResolvedCaster(
            casterClass: 'App\\TestCaster',
            args: []
        );

        static::assertInstanceOf(\Stringable::class, $sut);
    }

    public function testItReturnsStringRepresentationWithEmptyArgs(): void
    {
        $casterClass = 'App\\TestCaster';
        $sut = new ResolvedCaster(
            casterClass: $casterClass,
            args: []
        );

        $expected = $casterClass . ' (' . md5(serialize([])) . ')';
        static::assertSame($expected, (string)$sut);
    }

    public function testItReturnsStringRepresentationWithArgs(): void
    {
        $casterClass = 'App\\Utils\\Casts\\Casters\\DateCaster';
        $args = ['format' => 'Y-m-d H:i:s'];
        $sut = new ResolvedCaster(
            casterClass: $casterClass,
            args: $args
        );

        $expected = $casterClass . ' (' . md5(serialize($args)) . ')';
        static::assertSame($expected, (string)$sut);
    }

    public function testItIncludesArgsInHashForDifferentInstances(): void
    {
        $casterClass = 'App\\TestCaster';
        $sut1 = new ResolvedCaster(
            casterClass: $casterClass,
            args: ['option' => 'value1']
        );
        $sut2 = new ResolvedCaster(
            casterClass: $casterClass,
            args: ['option' => 'value2']
        );

        static::assertNotSame((string)$sut1, (string)$sut2);
    }

    public function testItProducesIdenticalHashForIdenticalArgs(): void
    {
        $casterClass = 'App\\TestCaster';
        $args = ['key' => 'value'];
        $sut1 = new ResolvedCaster(
            casterClass: $casterClass,
            args: $args
        );
        $sut2 = new ResolvedCaster(
            casterClass: $casterClass,
            args: $args
        );

        static::assertSame((string)$sut1, (string)$sut2);
    }

    public function testItHandlesComplexArgs(): void
    {
        $casterClass = 'App\\ComplexCaster';
        $args = [
            'nested' => ['key' => 'value', 'number' => 42],
            'array' => [1, 2, 3],
            'string' => 'test',
        ];
        $sut = new ResolvedCaster(
            casterClass: $casterClass,
            args: $args
        );

        $expected = $casterClass . ' (' . md5(serialize($args)) . ')';
        static::assertSame($expected, (string)$sut);
    }
}
