<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Values;

use App\Services\Storage\Values\FileType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(FileType::class)]
class FileTypeTest extends TestCase
{
    // =========================================================================
    // fromMimeType — image
    // =========================================================================

    public static function provideTestItFromMimeTypeImageData(): iterable
    {
        yield 'image/jpeg' => ['image/jpeg'];
        yield 'image/png' => ['image/png'];
        yield 'image/gif' => ['image/gif'];
        yield 'image/webp' => ['image/webp'];
        yield 'image/svg+xml' => ['image/svg+xml'];
    }

    #[DataProvider('provideTestItFromMimeTypeImageData')]
    public function testItFromMimeTypeImage(string $mimeType): void
    {
        static::assertSame(FileType::IMAGE, FileType::fromMimeType($mimeType));
    }

    // =========================================================================
    // fromMimeType — video / audio
    // =========================================================================

    public function testItFromMimeTypeVideo(): void
    {
        static::assertSame(FileType::VIDEO, FileType::fromMimeType('video/mp4'));
    }

    public function testItFromMimeTypeAudio(): void
    {
        static::assertSame(FileType::AUDIO, FileType::fromMimeType('audio/mpeg'));
    }

    // =========================================================================
    // fromMimeType — word documents
    // =========================================================================

    public static function provideTestItFromMimeTypeWordData(): iterable
    {
        yield 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        yield 'application/msword' => ['application/msword'];
    }

    #[DataProvider('provideTestItFromMimeTypeWordData')]
    public function testItFromMimeTypeWordDocument(string $mimeType): void
    {
        static::assertSame(FileType::WORD_DOCUMENT, FileType::fromMimeType($mimeType));
    }

    // =========================================================================
    // fromMimeType — PDF
    // =========================================================================

    public function testItFromMimeTypePdf(): void
    {
        static::assertSame(FileType::PDF, FileType::fromMimeType('application/pdf'));
    }

    // =========================================================================
    // fromMimeType — plain text
    // =========================================================================

    public static function provideTestItFromMimeTypePlainTextData(): iterable
    {
        yield 'text/plain' => ['text/plain'];
        yield 'text/html' => ['text/html'];
        yield 'application/json' => ['application/json'];
        yield 'text/markdown' => ['text/markdown'];
        yield 'text/x-python' => ['text/x-python'];
        yield 'text/css' => ['text/css'];
        yield 'text/javascript' => ['text/javascript'];
    }

    #[DataProvider('provideTestItFromMimeTypePlainTextData')]
    public function testItFromMimeTypePlainText(string $mimeType): void
    {
        static::assertSame(FileType::PLAIN_TEXT, FileType::fromMimeType($mimeType));
    }

    // =========================================================================
    // fromMimeType — fallback
    // =========================================================================

    public function testItFromMimeTypeReturnsOtherForUnknownType(): void
    {
        static::assertSame(FileType::OTHER, FileType::fromMimeType('application/x-unknown-binary'));
    }

    public function testItFromMimeTypeReturnsOtherForEmptyString(): void
    {
        static::assertSame(FileType::OTHER, FileType::fromMimeType(''));
    }
}
