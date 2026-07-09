<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Values;

use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\PlainTextLanguageType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FileCollection::class)]
class FileCollectionTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructsEmpty(): void
    {
        $sut = new FileCollection();

        static::assertSame(0, $sut->count());
    }

    public function testItConstructsWithFiles(): void
    {
        $sut = new FileCollection(
            $this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'),
            $this->makeFile('b.png', FileType::IMAGE, 'png', 'image/png'),
        );

        static::assertSame(2, $sut->count());
    }

    public function testItConstructsFlattenNestedCollections(): void
    {
        $inner = new FileCollection(
            $this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'),
            $this->makeFile('b.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'),
        );

        $sut = new FileCollection(
            $this->makeFile('c.png', FileType::IMAGE, 'png', 'image/png'),
            $inner,
        );

        static::assertSame(3, $sut->count());
    }

    public function testItConstructsMergesTwoCollections(): void
    {
        $a = new FileCollection($this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'));
        $b = new FileCollection($this->makeFile('b.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'));

        $merged = new FileCollection($a, $b);

        static::assertSame(2, $merged->count());
    }

    // =========================================================================
    // getFirst
    // =========================================================================

    public function testItGetFirstReturnsNullWhenEmpty(): void
    {
        $sut = new FileCollection();

        static::assertNull($sut->getFirst());
    }

    public function testItGetFirstReturnsFirstFile(): void
    {
        $first = $this->makeFile('first.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain');
        $second = $this->makeFile('second.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain');

        $sut = new FileCollection($first, $second);

        static::assertSame($first, $sut->getFirst());
    }

    // =========================================================================
    // filterByMediaType
    // =========================================================================

    public function testItFilterByMediaTypeReturnsMatchingFiles(): void
    {
        $image = $this->makeFile('a.png', FileType::IMAGE, 'png', 'image/png');
        $text = $this->makeFile('b.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain');

        $sut = new FileCollection($image, $text);
        $filtered = $sut->filterByMediaType(FileType::IMAGE);

        static::assertSame(1, $filtered->count());
        static::assertSame($image, $filtered->getFirst());
    }

    public function testItFilterByMediaTypeReturnsEmptyCollectionWhenNoMatch(): void
    {
        $sut = new FileCollection($this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'));

        $filtered = $sut->filterByMediaType(FileType::IMAGE);

        static::assertSame(0, $filtered->count());
    }

    public function testItFilterByMediaTypeDoesNotMutateOriginal(): void
    {
        $sut = new FileCollection(
            $this->makeFile('a.png', FileType::IMAGE, 'png', 'image/png'),
            $this->makeFile('b.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain'),
        );

        $sut->filterByMediaType(FileType::IMAGE);

        static::assertSame(2, $sut->count());
    }

    // =========================================================================
    // filterByExtension
    // =========================================================================

    public function testItFilterByExtensionMatchesStoredFileExtracts(): void
    {
        $pdf = $this->makeExtract('output/001-extract.md', FileType::PLAIN_TEXT, 'md', 'text/markdown');
        $json = $this->makeExtract('output/002-data.json', FileType::PLAIN_TEXT, 'json', 'application/json');

        $sut = new FileCollection($pdf, $json);
        $filtered = $sut->filterByExtension('md');

        static::assertSame(1, $filtered->count());
    }

    // =========================================================================
    // filterByMimetype
    // =========================================================================

    public function testItFilterByMimetypeReturnsMatchingFiles(): void
    {
        $png = $this->makeFile('a.png', FileType::IMAGE, 'png', 'image/png');
        $md = $this->makeFile('b.md', FileType::PLAIN_TEXT, 'md', 'text/markdown');

        $sut = new FileCollection($png, $md);
        $filtered = $sut->filterByMimetype('text/markdown');

        static::assertSame(1, $filtered->count());
        static::assertSame($md, $filtered->getFirst());
    }

    // =========================================================================
    // Iteration / toArray / jsonSerialize
    // =========================================================================

    public function testItIsIterable(): void
    {
        $file = $this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain');
        $sut = new FileCollection($file);

        $collected = [];
        foreach ($sut as $item) {
            $collected[] = $item;
        }

        static::assertSame([$file], $collected);
    }

    public function testItToArrayReturnsAllFiles(): void
    {
        $a = $this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain');
        $b = $this->makeFile('b.png', FileType::IMAGE, 'png', 'image/png');

        $sut = new FileCollection($a, $b);

        static::assertSame([$a, $b], $sut->toArray());
    }

    public function testItJsonSerializeReturnsAllFiles(): void
    {
        $a = $this->makeFile('a.txt', FileType::PLAIN_TEXT, 'txt', 'text/plain');
        $b = $this->makeFile('b.png', FileType::IMAGE, 'png', 'image/png');

        $sut = new FileCollection($a, $b);

        static::assertSame([$a, $b], $sut->jsonSerialize());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeFile(string $originalFilename, FileType $fileType, string $extension, string $mimeType): FileInterface
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getOriginalFilename')->willReturn($originalFilename);
        $file->method('getFileType')->willReturn($fileType);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getPlainTextLanguageType')->willReturn(null);
        return $file;
    }

    private function makeExtract(string $diskPath, FileType $fileType, string $extension, string $mimeType): \App\Services\Storage\Values\StoredFileExtract
    {
        $filesystem = $this->createStub(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $filesystem->method('get')->willReturn('content');
        $filesystem->method('size')->willReturn(100);

        return \App\Services\Storage\Values\StoredFileExtract::fromJson(
            [
                'diskFilename' => $diskPath,
                'type' => $fileType->value,
                'extension' => $extension,
                'mimetype' => $mimeType,
                'size' => 100,
                'languageType' => null,
            ],
            '/source/folder',
            $filesystem
        );
    }
}
