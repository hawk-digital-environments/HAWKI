<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Handlers;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\FileConverter\Handlers\HawkiDocConverter;
use App\Services\Storage\Values\FileReference;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(HawkiDocConverter::class)]
class HawkiDocConverterTest extends TestCase
{
    private const API_URL = 'http://hawki-converter.test/convert';
    private const API_KEY = 'test-api-key';

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(?Repository $cache = null): HawkiDocConverter
    {
        $sut = new HawkiDocConverter($cache ?? $this->makeCache());
        $sut->setConfig(['api_url' => self::API_URL, 'api_key' => self::API_KEY]);
        return $sut;
    }

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

    /**
     * Builds an in-memory ZIP archive and returns its raw binary content.
     *
     * @param array<string, string> $entries Map of filename => content.
     */
    private function makeZipContent(array $entries): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'hawki_test_zip_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE);
        foreach ($entries as $filename => $content) {
            $zip->addFromString($filename, $content);
        }
        $zip->close();

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(HawkiDocConverter::class, new HawkiDocConverter($this->makeCache()));
    }

    // =========================================================================
    // isValidConfig
    // =========================================================================

    #[DataProvider('provideTestItIsValidConfigData')]
    public function testItIsValidConfig(array $config, bool $expected): void
    {
        static::assertSame($expected, HawkiDocConverter::isValidConfig($config));
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

    public function testItGetAllowedMimeTypesReturnsPdfAndDocTypes(): void
    {
        Http::fake([
            'http://hawki-converter.test' => Http::response([
                'supported_formats' => ['pdf', 'doc', 'docx'],
            ], 200),
        ]);

        $types = $this->makeSut()->getAllowedMimeTypes();

        static::assertIsArray($types);
        static::assertContains('application/pdf', $types);
        // docx MIME type
        static::assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $types);
    }

    // =========================================================================
    // convert — happy path
    // =========================================================================

    public function testItConvertExtractsFilesFromZipResponse(): void
    {
        $zipContent = $this->makeZipContent([
            'document.md' => '# Extracted Content',
            'image.png' => 'fake-png-bytes',
        ]);

        Http::fake([
            self::API_URL => Http::response($zipContent, 200, ['Content-Type' => 'application/zip']),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('report.pdf', '%PDF-1.4'));

        static::assertCount(2, $result);
    }

    public function testItConvertReturnsFileReferencesWithCorrectFilenames(): void
    {
        $zipContent = $this->makeZipContent(['document.md' => '# Content']);

        Http::fake([
            self::API_URL => Http::response($zipContent, 200),
        ]);

        $result = $this->makeSut()->convert(FileReference::fromContent('report.pdf', '%PDF-1.4'));

        $files = iterator_to_array($result);
        static::assertSame('document.md', $files[0]->getOriginalFilename());
    }

    public function testItConvertSendsAuthorizationHeader(): void
    {
        $zipContent = $this->makeZipContent(['doc.md' => 'content']);

        Http::fake([
            self::API_URL => Http::response($zipContent, 200),
        ]);

        $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer ' . self::API_KEY);
        });
    }

    // =========================================================================
    // convert — error handling
    // =========================================================================

    public function testItConvertThrowsConversionFailedExceptionOnNonSuccessResponse(): void
    {
        Http::fake([
            self::API_URL => Http::response('Forbidden', 403),
        ]);

        $this->expectException(ConversionFailedException::class);
        $this->expectExceptionMessageMatches('/403/');

        $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));
    }

    public function testItConvertThrowsConversionFailedExceptionWhenResponseIsNotValidZip(): void
    {
        Http::fake([
            self::API_URL => Http::response('this is not a zip file', 200),
        ]);

        $this->expectException(ConversionFailedException::class);
        $this->expectExceptionMessageMatches('/ZIP/i');

        $this->makeSut()->convert(FileReference::fromContent('doc.pdf', '%PDF-1.4'));
    }
}
