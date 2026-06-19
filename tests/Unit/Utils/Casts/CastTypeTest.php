<?php
declare(strict_types=1);


namespace Tests\Unit\Utils\Casts;

use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CastType::class)]
#[CoversClass(InvalidCastTypeException::class)]
class CastTypeTest extends TestCase
{
    public function testItCanBeConstructedFromString(): void
    {
        $sut = CastType::fromString('datetime');
        static::assertSame(CastType::DATETIME, $sut);
    }

    public function testItCanBeConstructedFromAliasString(): void
    {
        $sut = CastType::fromString('integer');
        static::assertSame(CastType::INT, $sut);
    }

    public function testItCanBeConstructedWithCaseInsensitiveTypeString(): void
    {
        $sut = CastType::fromString('BoOl');
        static::assertSame(CastType::BOOL, $sut);
    }

    public function testItFailsToConstructWithInvalidString(): void
    {
        $this->expectException(InvalidCastTypeException::class);
        CastType::fromString('not_a_valid_type');
    }

}
