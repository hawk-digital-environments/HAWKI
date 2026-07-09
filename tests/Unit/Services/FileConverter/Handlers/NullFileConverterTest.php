<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Handlers;

use App\Services\FileConverter\Handlers\NullFileConverter;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullFileConverter::class)]
class NullFileConverterTest extends TestCase
{
    public function testItConstructs(): void
    {
        $sut = new NullFileConverter();
        static::assertInstanceOf(NullFileConverter::class, $sut);
    }

    public function testItImplementsFileConverterInterface(): void
    {
        static::assertInstanceOf(FileConverterInterface::class, new NullFileConverter());
    }

    // =========================================================================
    // isValidConfig
    // =========================================================================

    public function testItIsValidConfigReturnsTrueForAnyInput(): void
    {
        static::assertTrue(NullFileConverter::isValidConfig([]));
        static::assertTrue(NullFileConverter::isValidConfig(['foo' => 'bar']));
    }

    // =========================================================================
    // setConfig
    // =========================================================================

    public function testItSetConfigDoesNothing(): void
    {
        $sut = new NullFileConverter();
        $sut->setConfig(['foo' => 'bar']);

        // No exception means success; the method is intentionally a no-op.
        static::assertInstanceOf(NullFileConverter::class, $sut);
    }

    // =========================================================================
    // isAvailable
    // =========================================================================

    public function testItIsNotAvailable(): void
    {
        static::assertFalse((new NullFileConverter())->isAvailable());
    }

    // =========================================================================
    // getAllowedMimeTypes
    // =========================================================================

    public function testItGetAllowedMimeTypesReturnsEmptyArray(): void
    {
        static::assertSame([], (new NullFileConverter())->getAllowedMimeTypes());
    }

    // =========================================================================
    // canConvertMimetype
    // =========================================================================

    public function testItCannotConvertAnyMimetype(): void
    {
        $sut = new NullFileConverter();

        static::assertFalse($sut->canConvertMimetype('application/pdf'));
        static::assertFalse($sut->canConvertMimetype('image/png'));
        static::assertFalse($sut->canConvertMimetype(''));
    }

    // =========================================================================
    // wouldLikeSomeoneElseToConvertMimetype
    // =========================================================================

    public function testItWouldLikeSomeoneElseToConvertMimetypeReturnsFalse(): void
    {
        $sut = new NullFileConverter();

        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('application/pdf'));
        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('image/svg+xml'));
    }

    // =========================================================================
    // convert
    // =========================================================================

    public function testItConvertReturnsEmptyFileCollection(): void
    {
        $sut = new NullFileConverter();
        $file = FileReference::fromContent('test.txt', 'hello');

        $result = $sut->convert($file);

        static::assertInstanceOf(FileCollection::class, $result);
        static::assertCount(0, $result);
    }
}
