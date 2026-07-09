<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\FileConverter\Handlers\GwdgDoclingConverter;
use App\Services\Storage\Values\FileReference;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(GwdgDoclingConverter::class)]
class GwdgDoclingConverterTest extends TestCase
{
    private const API_URL = 'http://docling.test/convert';
    private const API_KEY = 'test-api-key';

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(): GwdgDoclingConverter
    {
        $sut = new GwdgDoclingConverter();
        $sut->setConfig(['api_url' => self::API_URL, 'api_key' => self::API_KEY]);
        return $sut;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(GwdgDoclingConverter::class, new GwdgDoclingConverter());
    }

    // =========================================================================
    // isValidConfig
    // =========================================================================

    #[DataProvider('provideTestItIsValidConfigData')]
    public function testItIsValidConfig(array $config, bool $expected): void
    {
        static::assertSame($expected, GwdgDoclingConverter::isValidConfig($config));
    }

    public static function provideTestItIsValidConfigData(): iterable
    {
        yield 'valid config' => [['api_url' => 'https://api.example.com', 'api_key' => 'secret'], true];
        yield 'missing api_url' => [['api_key' => 'secret'], false];
        yield 'missing api_key' => [['api_url' => 'https://api.example.com'], false];
        yield 'empty api_key' => [['api_url' => 'https://api.example.com', 'api_key' => ''], false];
        yield 'invalid url' => [['api_url' => 'not-a-url', 'api_key' => 'secret'], false];
        yield 'empty config' => [[], false];
    }

    // =========================================================================
    // isAvailable
    // =========================================================================

    public function testItIsAvailable(): void
    {
        static::assertTrue($this->makeSut()->isAvailable());
    }

    // =========================================================================
    // getAllowedMimeTypes
    // =========================================================================

    public function testItGetAllowedMimeTypesReturnsNonEmptyArray(): void
    {
        $types = $this->makeSut()->getAllowedMimeTypes();

        static::assertIsArray($types);
        static::assertNotEmpty($types);
    }

    public function testItGetAllowedMimeTypesIncludesPdf(): void
    {
        $types = $this->makeSut()->getAllowedMimeTypes();

        static::assertContains('application/pdf', $types);
    }

    public function testItGetAllowedMimeTypesIncludesPng(): void
    {
        $types = $this->makeSut()->getAllowedMimeTypes();

        static::assertContains('image/png', $types);
    }

    public function testItGetAllowedMimeTypesContainsNoDuplicates(): void
    {
        $types = $this->makeSut()->getAllowedMimeTypes();

        static::assertSame(array_values(array_unique($types)), $types);
    }

    // =========================================================================
    // convert — happy path
    // =========================================================================

    public function testItConvertReturnsMarkdownFileReference(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'filename' => 'report',
                'markdown' => '# Report Content',
                'images' => [],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('report.pdf', '%PDF-1.4'));

        $files = iterator_to_array($result);
        static::assertCount(1, $files);
        static::assertStringEndsWith('.md', $files[0]->getOriginalFilename());
        static::assertStringContainsString('Report Content', $files[0]->getContent());
    }

    public function testItConvertUsesFilenameFromResponseForMarkdownFile(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'filename' => 'my_document',
                'markdown' => '# Content',
                'images' => [],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        $files = iterator_to_array($result);
        static::assertSame('my_document.md', $files[0]->getOriginalFilename());
    }

    public function testItConvertFallsBackToDocumentFilenameWhenResponseHasNone(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'markdown' => '# Content',
                'images' => [],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        $files = iterator_to_array($result);
        static::assertSame('document.md', $files[0]->getOriginalFilename());
    }

    public function testItConvertDecodesBase64DataUriImages(): void
    {
        $imageContent = 'fake-png-content';
        $dataUri = 'data:image/png;base64,' . base64_encode($imageContent);

        Http::fake([
            self::API_URL => Http::response([
                'markdown' => '',
                'images' => [
                    ['filename' => 'page1.png', 'image' => $dataUri],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        $files = iterator_to_array($result);
        static::assertCount(1, $files);
        static::assertSame('page1.png', $files[0]->getOriginalFilename());
        static::assertSame($imageContent, $files[0]->getContent());
    }

    public function testItConvertDecodesPlainBase64Images(): void
    {
        $imageContent = 'fake-png-content';

        Http::fake([
            self::API_URL => Http::response([
                'markdown' => '',
                'images' => [
                    ['filename' => 'page1.png', 'image' => base64_encode($imageContent)],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        $files = iterator_to_array($result);
        static::assertCount(1, $files);
        static::assertSame($imageContent, $files[0]->getContent());
    }

    public function testItConvertSkipsImagesWithEmptyImageField(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'markdown' => '',
                'images' => [
                    ['filename' => 'page1.png', 'image' => ''],
                ],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        static::assertCount(0, $result);
    }

    public function testItConvertReturnsEmptyCollectionWhenResponseHasNoMarkdownAndNoImages(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'markdown' => '',
                'images' => [],
            ], 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        static::assertCount(0, $result);
    }

    // =========================================================================
    // convert — error handling
    // =========================================================================

    public function testItConvertThrowsConversionFailedExceptionOnNonSuccessResponse(): void
    {
        Http::fake([
            self::API_URL => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(ConversionFailedException::class);
        $this->expectExceptionMessageMatches('/401/');

        $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));
    }
}
