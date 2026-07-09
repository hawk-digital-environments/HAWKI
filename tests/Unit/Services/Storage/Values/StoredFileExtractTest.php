<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Values;

use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\PlainTextLanguageType;
use App\Services\Storage\Values\StoredFileExtract;
use Illuminate\Contracts\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(StoredFileExtract::class)]
class StoredFileExtractTest extends TestCase
{
    // =========================================================================
    // fromJson
    // =========================================================================

    public function testItFromJsonBuildsExtract(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001-extract.md',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'md',
                'mimetype' => 'text/markdown',
                'size' => 1234,
                'languageType' => null,
            ],
            '/source/folder',
            $filesystem
        );

        static::assertSame('md', $sut->getExtension());
        static::assertSame('text/markdown', $sut->getMimeType());
        static::assertSame(1234, $sut->getSize());
        static::assertSame(FileType::PLAIN_TEXT, $sut->getFileType());
        static::assertNull($sut->getPlainTextLanguageType());
    }

    public function testItFromJsonWithLanguageType(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001-code.md',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'md',
                'mimetype' => 'text/markdown',
                'size' => 500,
                'languageType' => PlainTextLanguageType::PYTHON->value,
            ],
            '/source/folder',
            $filesystem
        );

        static::assertSame(PlainTextLanguageType::PYTHON, $sut->getPlainTextLanguageType());
    }

    public function testItFromJsonAcceptsStringInput(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $json = json_encode([
            'diskFilename' => 'output/001.md',
            'type' => FileType::PLAIN_TEXT->value,
            'extension' => 'md',
            'mimetype' => 'text/markdown',
            'size' => 42,
        ], JSON_THROW_ON_ERROR);

        $sut = StoredFileExtract::fromJson($json, '/folder', $filesystem);

        static::assertSame(42, $sut->getSize());
    }

    // =========================================================================
    // fromPlainTextFile
    // =========================================================================

    public function testItFromPlainTextFileForMarkdownDoesNotAddOverhead(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromPlainTextFile(
            storageDiskFilePath: '/source/folder/uuid.blob',
            sourceSize: 200,
            languageType: PlainTextLanguageType::MARKDOWN,
            filesystem: $filesystem
        );

        // Markdown is already formatted — no wrapping overhead added.
        static::assertSame(200, $sut->getSize());
        static::assertSame('md', $sut->getExtension());
        static::assertSame('text/markdown', $sut->getMimeType());
        static::assertSame(PlainTextLanguageType::MARKDOWN, $sut->getPlainTextLanguageType());
    }

    public function testItFromPlainTextFileForNonMarkdownAddsCodeBlockOverhead(): void
    {
        $filesystem = $this->createStub(Filesystem::class);
        $sourceSize = 100;

        $sut = StoredFileExtract::fromPlainTextFile(
            storageDiskFilePath: '/source/folder/uuid.blob',
            sourceSize: $sourceSize,
            languageType: PlainTextLanguageType::PYTHON,
            filesystem: $filesystem
        );

        // Code block wrapping adds "```python\n" + "\n```" = strlen("```python\n\n```") = 14 chars
        $expectedOverhead = strlen("```python\n\n```");
        static::assertSame($sourceSize + $expectedOverhead, $sut->getSize());
    }

    public function testItFromPlainTextFileSetsCorrectDiskPath(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromPlainTextFile(
            storageDiskFilePath: '/source/folder/uuid.blob',
            sourceSize: 50,
            languageType: PlainTextLanguageType::PYTHON,
            filesystem: $filesystem
        );

        static::assertSame('/source/folder/uuid.blob', $sut->getDiskFilePath());
    }

    // =========================================================================
    // getContent — code block wrapping
    // =========================================================================

    public function testItGetContentWrapsNonMarkdownInCodeBlock(): void
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('get')->willReturn('print("hello")');

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001.py',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'py',
                'mimetype' => 'text/x-python',
                'size' => 14,
                'languageType' => PlainTextLanguageType::PYTHON->value,
            ],
            '/source/folder',
            $filesystem
        );

        static::assertSame("```python\nprint(\"hello\")\n```", $sut->getContent());
    }

    public function testItGetContentDoesNotWrapMarkdown(): void
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('get')->willReturn('# Hello World');

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001.md',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'md',
                'mimetype' => 'text/markdown',
                'size' => 13,
                'languageType' => PlainTextLanguageType::MARKDOWN->value,
            ],
            '/source/folder',
            $filesystem
        );

        static::assertSame('# Hello World', $sut->getContent());
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializesCorrectly(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001-extract.md',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'md',
                'mimetype' => 'text/markdown',
                'size' => 500,
                'languageType' => PlainTextLanguageType::MARKDOWN->value,
            ],
            '/source/folder',
            $filesystem
        );

        $serialized = $sut->jsonSerialize();

        static::assertSame('output/001-extract.md', $serialized['diskFilename']);
        static::assertSame(FileType::PLAIN_TEXT->value, $serialized['type']);
        static::assertSame('md', $serialized['extension']);
        static::assertSame('text/markdown', $serialized['mimetype']);
        static::assertSame(500, $serialized['size']);
        static::assertSame(PlainTextLanguageType::MARKDOWN->value, $serialized['languageType']);
    }

    // =========================================================================
    // Path helpers
    // =========================================================================

    public function testItGetOriginalFilenameReturnsBasename(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001-extract.md',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'md',
                'mimetype' => 'text/markdown',
                'size' => 10,
            ],
            '/source/folder',
            $filesystem
        );

        static::assertSame('001-extract.md', $sut->getOriginalFilename());
    }

    public function testItGetDiskFilePathJoinsFolderAndFilename(): void
    {
        $filesystem = $this->createStub(Filesystem::class);

        $sut = StoredFileExtract::fromJson(
            [
                'diskFilename' => 'output/001-extract.md',
                'type' => FileType::PLAIN_TEXT->value,
                'extension' => 'md',
                'mimetype' => 'text/markdown',
                'size' => 10,
            ],
            '/source/folder',
            $filesystem
        );

        static::assertSame('/source/folder/output/001-extract.md', $sut->getDiskFilePath());
    }
}
