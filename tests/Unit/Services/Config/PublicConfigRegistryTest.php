<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config;

use App\Services\Config\Contracts\PublicConfigInterface;
use App\Services\Config\Registries\PublicConfigRegistry;
use App\Utils\Lists\LazySingletonList;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Config\PublicConfigRegistryTestFixtures\ConcretePublicConfig;

#[CoversClass(PublicConfigRegistry::class)]
class PublicConfigRegistryTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a registry backed by a real LazySingletonList whose factory instantiates
     * config classes via fromArray() — no container or ConfigService needed.
     */
    private function makeRegistry(): PublicConfigRegistry
    {
        /** @var LazySingletonList<class-string<PublicConfigInterface>, PublicConfigInterface> $list */
        $list = new LazySingletonList(
            fn(string $class) => $class,
            function (string $class): PublicConfigInterface {
                /** @var class-string<\App\Services\Config\AbstractConfig&PublicConfigInterface> $class */
                return $class::fromArray([]);
            },
        );
        return new PublicConfigRegistry($list);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeRegistry();

        static::assertInstanceOf(PublicConfigRegistry::class, $sut);
    }

    // =========================================================================
    // declare
    // =========================================================================

    public function testItDeclareReturnsSelf(): void
    {
        $sut = $this->makeRegistry();

        static::assertSame($sut, $sut->declare(ConcretePublicConfig::class));
    }

    public function testItDeclareThrowsWhenClassDoesNotImplementPublicConfigInterface(): void
    {
        $sut = $this->makeRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Public config class %s must implement %s',
            \stdClass::class,
            PublicConfigInterface::class,
        ));

        /** @phpstan-ignore argument.type */
        $sut->declare(\stdClass::class);
    }

    public function testItDeclareIsIdempotentForTheSameClass(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare(ConcretePublicConfig::class);
        $sut->declare(ConcretePublicConfig::class);

        static::assertCount(1, iterator_to_array($sut));
    }

    // =========================================================================
    // getIterator
    // =========================================================================

    public function testItGetIteratorYieldsNothingWhenNothingDeclared(): void
    {
        $sut = $this->makeRegistry();

        static::assertCount(0, iterator_to_array($sut));
    }

    public function testItGetIteratorYieldsDeclaredConfigInstance(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare(ConcretePublicConfig::class);

        $results = iterator_to_array($sut);

        static::assertCount(1, $results);
        static::assertInstanceOf(ConcretePublicConfig::class, array_values($results)[0]);
    }

    public function testItGetIteratorReturnsSameInstanceOnRepeatedIteration(): void
    {
        $sut = $this->makeRegistry();
        $sut->declare(ConcretePublicConfig::class);

        $first  = array_values(iterator_to_array($sut))[0];
        $second = array_values(iterator_to_array($sut))[0];

        static::assertSame($first, $second);
    }
}
