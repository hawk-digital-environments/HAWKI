<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Utils;

use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\FileConverter\Utils\ImagePreProcessingConverter;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ImagePreProcessingConverter::class)]
class ImagePreProcessingConverterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a cache mock that returns preset binary-availability values for the three
     * detection cache keys, and executes the callback for any other key.
     *
     * @return MockObject&Repository
     */
    private function makeCache(bool $svgAvailable = false, bool $imagickAvailable = false, bool $ghostscriptAvailable = false): MockObject
    {
        $cache = $this->createMock(Repository::class);
        $cache->method('remember')
            ->willReturnCallback(function ($key, $ttl, $callback) use ($svgAvailable, $imagickAvailable, $ghostscriptAvailable) {
                return match ($key) {
                    'common-image-format-extractor.canConvertSvg' => $svgAvailable,
                    'common-image-format-extractor.hasImagemagickCli' => $imagickAvailable,
                    'common-image-format-extractor.hasGhostscriptCli' => $ghostscriptAvailable,
                    default => $callback(),
                };
            });
        return $cache;
    }

    /**
     * Builds a mock of the inner FileConverterInterface with controllable behaviour.
     *
     * @param string[] $mimeTypes  MIME types the inner converter claims to support.
     * @return MockObject&FileConverterInterface
     */
    private function makeInnerConverter(
        bool  $available = true,
        array $mimeTypes = [],
        bool  $wouldLikeSomeoneElse = false,
    ): MockObject {
        $mock = $this->createMock(FileConverterInterface::class);
        $mock->method('isAvailable')->willReturn($available);
        $mock->method('getAllowedMimeTypes')->willReturn($mimeTypes);
        $mock->method('canConvertMimetype')
            ->willReturnCallback(fn(string $mime) => in_array($mime, $mimeTypes, true));
        $mock->method('wouldLikeSomeoneElseToConvertMimetype')->willReturn($wouldLikeSomeoneElse);
        return $mock;
    }

    private function makeSut(
        FileConverterInterface $innerConverter = null,
        Repository             $cache = null,
        LoggerInterface        $logger = null,
    ): ImagePreProcessingConverter {
        return new ImagePreProcessingConverter(
            concreteConverter: $innerConverter ?? $this->makeInnerConverter(),
            logger: $logger ?? $this->createMock(LoggerInterface::class),
            cache: $cache ?? $this->makeCache(),
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(ImagePreProcessingConverter::class, $sut);
    }

    // =========================================================================
    // isValidConfig
    // =========================================================================

    public function testItIsValidConfigReturnsTrueForAnyInput(): void
    {
        static::assertTrue(ImagePreProcessingConverter::isValidConfig([]));
        static::assertTrue(ImagePreProcessingConverter::isValidConfig(['api_url' => 'http://example.com']));
    }

    // =========================================================================
    // setConfig
    // =========================================================================

    public function testItSetConfigDelegatesToInnerConverter(): void
    {
        $innerConverter = $this->createMock(FileConverterInterface::class);
        $innerConverter->expects(static::once())
            ->method('setConfig')
            ->with(['api_url' => 'http://example.com']);

        $sut = $this->makeSut(innerConverter: $innerConverter);
        $sut->setConfig(['api_url' => 'http://example.com']);
    }

    // =========================================================================
    // wouldLikeSomeoneElseToConvertMimetype
    // =========================================================================

    public function testItWouldLikeSomeoneElseToConvertMimetypeReturnsFalse(): void
    {
        $sut = $this->makeSut();

        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('image/svg+xml'));
        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('application/pdf'));
    }

    // =========================================================================
    // isAvailable
    // =========================================================================

    public function testItIsAvailableWhenInnerConverterIsAvailable(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(available: true),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        static::assertTrue($sut->isAvailable());
    }

    public function testItIsAvailableWhenSvgBinaryIsPresent(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(available: false),
            cache: $this->makeCache(svgAvailable: true, imagickAvailable: false),
        );

        static::assertTrue($sut->isAvailable());
    }

    public function testItIsAvailableWhenImagickBinaryIsPresent(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(available: false),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: true),
        );

        static::assertTrue($sut->isAvailable());
    }

    public function testItIsNotAvailableWhenNothingIsAvailable(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(available: false),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        static::assertFalse($sut->isAvailable());
    }

    // =========================================================================
    // getAllowedMimeTypes / canConvertMimetype
    // =========================================================================

    public function testItGetAllowedMimeTypesIncludesInnerConverterTypesWhenNoBinariesPresent(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(mimeTypes: ['application/pdf', 'image/png']),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        $types = $sut->getAllowedMimeTypes();

        static::assertContains('application/pdf', $types);
        static::assertContains('image/png', $types);
    }

    public function testItGetAllowedMimeTypesIncludesSvgWhenSvgBinaryPresent(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(mimeTypes: []),
            cache: $this->makeCache(svgAvailable: true, imagickAvailable: false),
        );

        $types = $sut->getAllowedMimeTypes();

        static::assertContains('image/svg+xml', $types);
    }

    public function testItGetAllowedMimeTypesDoesNotIncludeSvgWhenBinaryAbsent(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(mimeTypes: []),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        static::assertNotContains('image/svg+xml', $sut->getAllowedMimeTypes());
    }

    public function testItGetAllowedMimeTypesIncludesImagickTypesWhenImagickPresent(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(mimeTypes: []),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: true, ghostscriptAvailable: false),
        );

        $types = $sut->getAllowedMimeTypes();

        // TIFF is a core ImageMagick type; it should always appear when imagick is available.
        static::assertContains('image/tiff', $types);
    }

    public function testItCanConvertMimetypeDelegatesToGetAllowedMimeTypes(): void
    {
        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(mimeTypes: ['application/pdf']),
            cache: $this->makeCache(),
        );

        static::assertTrue($sut->canConvertMimetype('application/pdf'));
        static::assertFalse($sut->canConvertMimetype('text/html'));
    }

    // =========================================================================
    // convert — delegation to inner converter
    // =========================================================================

    public function testItConvertDelegatesToInnerConverterWhenItCanHandleFile(): void
    {
        $file = FileReference::fromContent('hello.txt', 'hello world');
        $expectedResult = new FileCollection(FileReference::fromContent('output.md', '# Result'));

        $innerConverter = $this->makeInnerConverter(
            available: true,
            mimeTypes: ['text/plain'],
            wouldLikeSomeoneElse: false,
        );
        $innerConverter->expects(static::once())
            ->method('convert')
            ->with($file)
            ->willReturn($expectedResult);

        $sut = $this->makeSut(
            innerConverter: $innerConverter,
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        $result = $sut->convert($file);

        static::assertSame($expectedResult, $result);
    }

    public function testItConvertReturnsEmptyCollectionWhenInnerConverterIsUnavailable(): void
    {
        $file = FileReference::fromContent('hello.txt', 'hello world');

        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(available: false, mimeTypes: []),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        $result = $sut->convert($file);

        static::assertInstanceOf(FileCollection::class, $result);
        static::assertCount(0, $result);
    }

    public function testItConvertReturnsEmptyCollectionWhenInnerConverterCannotHandleFiletype(): void
    {
        $file = FileReference::fromContent('hello.txt', 'hello world');

        $sut = $this->makeSut(
            innerConverter: $this->makeInnerConverter(available: true, mimeTypes: []),
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        $result = $sut->convert($file);

        static::assertCount(0, $result);
    }

    public function testItConvertStillCallsInnerConverterAsFallbackWhenItYieldedControlButNoPreprocessorWasAvailable(): void
    {
        // When wouldLikeSomeoneElse=true, the preprocessor is preferred. But if no binary
        // is installed, the inner converter is still used as a last resort — as long as it
        // can technically handle the MIME type.
        $file = FileReference::fromContent('hello.txt', 'hello world');
        $expectedResult = new FileCollection(FileReference::fromContent('output.md', '# fallback'));

        $innerConverter = $this->makeInnerConverter(
            available: true,
            mimeTypes: ['text/plain'],
            wouldLikeSomeoneElse: true,
        );
        $innerConverter->expects(static::once())
            ->method('convert')
            ->willReturn($expectedResult);

        $sut = $this->makeSut(
            innerConverter: $innerConverter,
            cache: $this->makeCache(svgAvailable: false, imagickAvailable: false),
        );

        $result = $sut->convert($file);

        static::assertSame($expectedResult, $result);
    }

    // =========================================================================
    // canConvertSvg / canConvertWithImagick / canConvertWithGhostscript
    // =========================================================================

    public function testItCanConvertSvgReturnsCachedValue(): void
    {
        $sut = $this->makeSut(cache: $this->makeCache(svgAvailable: true));
        static::assertTrue($sut->canConvertSvg());
    }

    public function testItCanConvertSvgReturnsFalseWhenBinaryAbsent(): void
    {
        $sut = $this->makeSut(cache: $this->makeCache(svgAvailable: false));
        static::assertFalse($sut->canConvertSvg());
    }

    public function testItCanConvertWithImagickReturnsCachedValue(): void
    {
        $sut = $this->makeSut(cache: $this->makeCache(imagickAvailable: true));
        static::assertTrue($sut->canConvertWithImagick());
    }

    public function testItCanConvertWithImagickReturnsFalseWhenBinaryAbsent(): void
    {
        $sut = $this->makeSut(cache: $this->makeCache(imagickAvailable: false));
        static::assertFalse($sut->canConvertWithImagick());
    }

    public function testItCanConvertWithGhostscriptReturnsCachedValue(): void
    {
        $sut = $this->makeSut(cache: $this->makeCache(ghostscriptAvailable: true));
        static::assertTrue($sut->canConvertWithGhostscript());
    }

    public function testItCanConvertWithGhostscriptReturnsFalseWhenBinaryAbsent(): void
    {
        $sut = $this->makeSut(cache: $this->makeCache(ghostscriptAvailable: false));
        static::assertFalse($sut->canConvertWithGhostscript());
    }
}
