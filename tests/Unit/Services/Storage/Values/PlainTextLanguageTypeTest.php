<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Values;

use App\Services\Storage\Values\PlainTextLanguageType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(PlainTextLanguageType::class)]
class PlainTextLanguageTypeTest extends TestCase
{
    // =========================================================================
    // tryFromFilename
    // =========================================================================

    public static function provideTestItTryFromFilenameData(): iterable
    {
        yield 'python .py' => ['script.py', PlainTextLanguageType::PYTHON];
        yield 'markdown .md' => ['readme.md', PlainTextLanguageType::MARKDOWN];
        yield 'javascript .js' => ['app.js', PlainTextLanguageType::JS];
        yield 'typescript .ts' => ['index.ts', PlainTextLanguageType::TYPESCRIPT];
        yield 'php .php' => ['controller.php', PlainTextLanguageType::PHP];
        yield 'rust .rs' => ['main.rs', PlainTextLanguageType::RUST];
        yield 'yaml .yml' => ['config.yml', PlainTextLanguageType::YAML];
        yield 'yaml .yaml' => ['config.yaml', PlainTextLanguageType::YAML];
        yield 'json .json' => ['data.json', PlainTextLanguageType::JSON];
        yield 'plain text .txt' => ['notes.txt', PlainTextLanguageType::TEXT];
        yield 'csv .csv' => ['data.csv', PlainTextLanguageType::TEXT];
        yield 'shell .sh' => ['deploy.sh', PlainTextLanguageType::BASH];
        yield 'css .css' => ['style.css', PlainTextLanguageType::CSS];
        yield 'scss .scss' => ['theme.scss', PlainTextLanguageType::SCSS];
        yield 'kotlin .kt' => ['Main.kt', PlainTextLanguageType::KOTLIN];
        yield 'dockerfile .dockerfile is not matched' => ['Dockerfile', null];
    }

    #[DataProvider('provideTestItTryFromFilenameData')]
    public function testItTryFromFilename(string $filename, PlainTextLanguageType|null $expected): void
    {
        static::assertSame($expected, PlainTextLanguageType::tryFromFilename($filename));
    }

    public function testItTryFromFilenameReturnsNullForUnknownExtension(): void
    {
        static::assertNull(PlainTextLanguageType::tryFromFilename('archive.zip'));
    }

    public function testItTryFromFilenameReturnsNullForEmptyString(): void
    {
        static::assertNull(PlainTextLanguageType::tryFromFilename(''));
    }

    // =========================================================================
    // tryFromMimetype
    // =========================================================================

    public static function provideTestItTryFromMimetypeData(): iterable
    {
        yield 'text/plain' => ['text/plain', PlainTextLanguageType::TEXT];
        yield 'text/markdown' => ['text/markdown', PlainTextLanguageType::MARKDOWN];
        yield 'text/x-markdown' => ['text/x-markdown', PlainTextLanguageType::MARKDOWN];
        yield 'application/json' => ['application/json', PlainTextLanguageType::JSON];
        yield 'text/html' => ['text/html', PlainTextLanguageType::HTML];
        yield 'text/x-python' => ['text/x-python', PlainTextLanguageType::PYTHON];
        yield 'text/x-php' => ['text/x-php', PlainTextLanguageType::PHP];
        yield 'text/css' => ['text/css', PlainTextLanguageType::CSS];
        yield 'text/javascript' => ['text/javascript', PlainTextLanguageType::JS];
        yield 'application/x-sh' => ['application/x-sh', PlainTextLanguageType::BASH];
        yield 'text/x-kotlin' => ['text/x-kotlin', PlainTextLanguageType::KOTLIN];
    }

    #[DataProvider('provideTestItTryFromMimetypeData')]
    public function testItTryFromMimetype(string $mimeType, PlainTextLanguageType $expected): void
    {
        static::assertSame($expected, PlainTextLanguageType::tryFromMimetype($mimeType));
    }

    public function testItTryFromMimetypeStripsCharsetParameter(): void
    {
        // MIME types like "text/plain; charset=utf-8" must still resolve correctly.
        static::assertSame(PlainTextLanguageType::TEXT, PlainTextLanguageType::tryFromMimetype('text/plain; charset=utf-8'));
    }

    public function testItTryFromMimetypeReturnsNullForUnknownMimeType(): void
    {
        static::assertNull(PlainTextLanguageType::tryFromMimetype('application/x-binary-unknown'));
    }

    public function testItTryFromMimetypeReturnsNullForImageMimeType(): void
    {
        static::assertNull(PlainTextLanguageType::tryFromMimetype('image/png'));
    }

    // =========================================================================
    // getMimeTypes
    // =========================================================================

    public function testItGetMimeTypesReturnsNonEmptyArray(): void
    {
        $result = PlainTextLanguageType::getMimeTypes();

        static::assertNotEmpty($result);
        static::assertContains('text/plain', $result);
        static::assertContains('text/html', $result);
        static::assertContains('application/json', $result);
    }

    public function testItGetMimeTypesAreAllStrings(): void
    {
        foreach (PlainTextLanguageType::getMimeTypes() as $mimeType) {
            static::assertIsString($mimeType);
            static::assertNotEmpty($mimeType);
        }
    }
}
