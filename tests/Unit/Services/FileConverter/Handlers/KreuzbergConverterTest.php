<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\FileConverter\Handlers\KreuzbergConverter;
use App\Services\Storage\Values\FileReference;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(KreuzbergConverter::class)]
class KreuzbergConverterTest extends TestCase
{
    private const API_URL = 'http://kreuzberg.test';

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return MockObject&Repository */
    private function makeCache(mixed $returnValue = null): MockObject
    {
        $cache = $this->createMock(Repository::class);
        $cache->method('remember')
            ->willReturnCallback(function ($key, $ttl, $callback) use ($returnValue) {
                return $returnValue ?? $callback();
            });
        return $cache;
    }

    private function makeSut(Repository $cache = null): KreuzbergConverter
    {
        $sut = new KreuzbergConverter($cache ?? $this->makeCache());
        $sut->setConfig(['api_url' => self::API_URL]);
        return $sut;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new KreuzbergConverter($this->makeCache());
        static::assertInstanceOf(KreuzbergConverter::class, $sut);
    }

    // =========================================================================
    // isValidConfig
    // =========================================================================

    #[DataProvider('provideTestItIsValidConfigData')]
    public function testItIsValidConfig(array $config, bool $expected): void
    {
        static::assertSame($expected, KreuzbergConverter::isValidConfig($config));
    }

    public static function provideTestItIsValidConfigData(): iterable
    {
        yield 'valid config with api_url' => [['api_url' => 'http://kreuzberg.test'], true];
        yield 'valid config with https url' => [['api_url' => 'https://kreuzberg.example.com'], true];
        yield 'missing api_url key' => [[], false];
        yield 'api_url is not a valid URL' => [['api_url' => 'not-a-url'], false];
        yield 'api_url is empty string' => [['api_url' => ''], false];
    }

    // =========================================================================
    // isAvailable
    // =========================================================================

    public function testItIsAvailable(): void
    {
        static::assertTrue($this->makeSut()->isAvailable());
    }

    // =========================================================================
    // wouldLikeSomeoneElseToConvertMimetype
    // =========================================================================

    public function testItWouldLikeSomeoneElseToConvertSvg(): void
    {
        static::assertTrue($this->makeSut()->wouldLikeSomeoneElseToConvertMimetype('image/svg+xml'));
    }

    public function testItWouldNotLikeSomeoneElseToConvertOtherTypes(): void
    {
        $sut = $this->makeSut();

        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('application/pdf'));
        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('image/png'));
    }

    // =========================================================================
    // getAllowedMimeTypes
    // =========================================================================

    public function testItGetAllowedMimeTypesFetchesFromApiAndCachesResult(): void
    {
        Http::fake([
            self::API_URL . '/formats' => Http::response([
                ['mime_type' => 'application/pdf', 'extension' => 'pdf'],
                ['mime_type' => 'image/png', 'extension' => 'png'],
            ], 200),
        ]);

        // Cache executes the callback so we get real results
        $sut = $this->makeSut($this->makeCache());

        $types = $sut->getAllowedMimeTypes();

        static::assertIsArray($types);
        static::assertContains('application/pdf', $types);
        static::assertContains('image/png', $types);
    }

    public function testItGetAllowedMimeTypesReturnsCachedResultWithoutHittingApi(): void
    {
        $cachedTypes = ['application/pdf', 'image/png'];
        $cache = $this->createMock(Repository::class);
        $cache->expects(static::once())
            ->method('remember')
            ->willReturn($cachedTypes);

        $sut = new KreuzbergConverter($cache);
        $sut->setConfig(['api_url' => self::API_URL]);

        $result = $sut->getAllowedMimeTypes();

        static::assertSame($cachedTypes, $result);
        Http::assertNothingSent();
    }

    // =========================================================================
    // convert — happy path
    // =========================================================================

    public function testItConvertReturnsMarkdownFileFromTextContent(): void
    {
        Http::fake([
            self::API_URL . '/extract' => Http::response([
                [
                    'content' => '# Extracted Text',
                    'images' => [],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('report.pdf', '%PDF-1.4'));

        $mdFiles = iterator_to_array($result);
        $mdFile = array_values(array_filter($mdFiles, fn($f) => str_ends_with($f->getOriginalFilename(), '_content.md')));

        static::assertCount(1, $mdFile);
        static::assertStringContainsString('Extracted Text', $mdFile[0]->getContent());
    }

    public function testItConvertReturnsImageFilesFromImagesArray(): void
    {
        $pngBytes = [137, 80, 78, 71, 13, 10, 26, 10]; // PNG magic bytes

        Http::fake([
            self::API_URL . '/extract' => Http::response([
                [
                    'content' => '',
                    'images' => [
                        [
                            'data' => $pngBytes,
                            'format' => 'png',
                            'image_index' => 0,
                            'page_number' => 1,
                            'is_mask' => false,
                            'ocr_result' => ['content' => ''],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        $allFiles = iterator_to_array($result);
        $imageFiles = array_values(array_filter($allFiles, fn($f) => str_ends_with($f->getOriginalFilename(), '.png')));

        static::assertCount(1, $imageFiles);
    }

    public function testItConvertAttachesOcrSidecarWhenOcrResultIsPresent(): void
    {
        $pngBytes = [137, 80, 78, 71, 13, 10, 26, 10];

        Http::fake([
            self::API_URL . '/extract' => Http::response([
                [
                    'content' => '',
                    'images' => [
                        [
                            'data' => $pngBytes,
                            'format' => 'png',
                            'image_index' => 0,
                            'page_number' => 1,
                            'is_mask' => false,
                            'ocr_result' => ['content' => 'OCR extracted text'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        $allFiles = iterator_to_array($result);
        $ocrFiles = array_values(array_filter($allFiles, fn($f) => str_ends_with($f->getOriginalFilename(), '_ocr.md')));

        static::assertCount(1, $ocrFiles);
        static::assertStringContainsString('OCR extracted text', $ocrFiles[0]->getContent());
    }

    public function testItConvertSkipsMaskImages(): void
    {
        $pngBytes = [137, 80, 78, 71, 13, 10, 26, 10];

        Http::fake([
            self::API_URL . '/extract' => Http::response([
                [
                    'content' => '',
                    'images' => [
                        ['data' => $pngBytes, 'format' => 'png', 'image_index' => 0, 'page_number' => 1, 'is_mask' => true, 'ocr_result' => ['content' => '']],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        static::assertCount(0, $result);
    }

    public function testItConvertSkipsImagesWithEmptyDataArray(): void
    {
        Http::fake([
            self::API_URL . '/extract' => Http::response([
                [
                    'content' => '',
                    'images' => [
                        ['data' => [], 'format' => 'png', 'image_index' => 0, 'page_number' => 1, 'is_mask' => false, 'ocr_result' => ['content' => '']],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        static::assertCount(0, $result);
    }

    public function testItConvertReturnsEmptyCollectionWhenContentIsEmptyAndNoImages(): void
    {
        Http::fake([
            self::API_URL . '/extract' => Http::response([
                ['content' => '', 'images' => []],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('empty.pdf', '%PDF-1.4'));

        static::assertCount(0, $result);
    }

    // =========================================================================
    // convert — error handling
    // =========================================================================

    public function testItConvertThrowsConversionFailedExceptionOnNonSuccessResponse(): void
    {
        Http::fake([
            self::API_URL . '/extract' => Http::response('Bad Request', 400),
        ]);

        $this->expectException(ConversionFailedException::class);
        $this->expectExceptionMessageMatches('/400/');

        $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));
    }
}
