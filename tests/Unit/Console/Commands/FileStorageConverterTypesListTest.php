<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\FileStorageConverterTypesList;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Console\Commands\Fixtures\FakeBackendConverter;
use Tests\Unit\Console\Commands\Fixtures\FakeWrappingConverter;

#[CoversClass(FileStorageConverterTypesList::class)]
class FileStorageConverterTypesListTest extends TestCase
{
    // =========================================================================
    // Availability guard
    // =========================================================================

    public function testItPrintsNothingWhenTheConverterIsUnavailable(): void
    {
        $this->bind(new FakeBackendConverter(mimeTypes: ['application/pdf'], available: false));

        $blocks = $this->parseBlocks($this->runCommand());

        self::assertSame([], $blocks);
    }

    // =========================================================================
    // Bare (non-decorating) converter
    // =========================================================================

    public function testItPrintsASingleBlockForABareNonExtensionConverter(): void
    {
        $this->bind(new FakeBackendConverter(mimeTypes: ['image/png', 'application/pdf']));

        $blocks = $this->parseBlocks($this->runCommand());

        self::assertCount(1, $blocks);
        self::assertSame(FakeBackendConverter::class, $blocks[0]['header']);
        // Types are sorted alphabetically by the command.
        self::assertSame(['application/pdf', 'image/png'], $blocks[0]['values']);
    }

    // =========================================================================
    // Per-layer diff (core invariant)
    // =========================================================================

    public function testItPrintsAPerLayerDiffInnermostFirst(): void
    {
        $backend = new FakeBackendConverter(mimeTypes: ['application/pdf']);
        $this->bind(new FakeWrappingConverter(
            inner: $backend,
            ownMimeTypes: ['image/tiff', 'image/svg+xml'],
        ));

        $blocks = $this->parseBlocks($this->runCommand());

        self::assertCount(2, $blocks);

        // Innermost converter is printed first.
        self::assertSame(FakeBackendConverter::class, $blocks[0]['header']);
        self::assertSame(FakeWrappingConverter::class, $blocks[1]['header']);

        // Backend contributes its own type.
        self::assertSame(['application/pdf'], $blocks[0]['values']);

        // The wrapper contributes only its additions (sorted), and never
        // re-lists a type already contributed by the inner converter.
        self::assertSame(['image/svg+xml', 'image/tiff'], $blocks[1]['values']);
        self::assertNotContains('application/pdf', $blocks[1]['values']);
    }

    public function testItHandlesAThreeLevelChain(): void
    {
        $backend = new FakeBackendConverter(mimeTypes: ['application/pdf']);
        $middle = new FakeWrappingConverter(inner: $backend, ownMimeTypes: ['image/png']);
        $outer = new FakeWrappingConverter(inner: $middle, ownMimeTypes: ['image/svg+xml']);
        $this->bind($outer);

        $blocks = $this->parseBlocks($this->runCommand());

        self::assertCount(3, $blocks);
        self::assertSame(FakeBackendConverter::class, $blocks[0]['header']);
        self::assertSame(FakeWrappingConverter::class, $blocks[1]['header']);
        self::assertSame(FakeWrappingConverter::class, $blocks[2]['header']);

        self::assertSame(['application/pdf'], $blocks[0]['values']);
        self::assertSame(['image/png'], $blocks[1]['values']);
        self::assertSame(['image/svg+xml'], $blocks[2]['values']);
    }

    public function testItShowsTheNoAddedTypesMessageForALayerThatAddsNothing(): void
    {
        $backend = new FakeBackendConverter(mimeTypes: ['application/pdf', 'image/png']);
        $this->bind(new FakeWrappingConverter(
            inner: $backend,
            ownMimeTypes: ['application/pdf'], // subset of the inner's types -> adds nothing
        ));

        $blocks = $this->parseBlocks($this->runCommand());

        self::assertCount(2, $blocks);
        self::assertSame(['application/pdf', 'image/png'], $blocks[0]['values']);
        self::assertSame(['(No added types)'], $blocks[1]['values']);
    }

    // =========================================================================
    // --extensions option
    // =========================================================================

    public function testItConvertsMimeTypesToExtensionsWithTheExtensionsFlag(): void
    {
        $this->bind(new FakeBackendConverter(mimeTypes: ['application/pdf']));

        $blocks = $this->parseBlocks($this->runCommand(['--extensions' => true]));

        self::assertCount(1, $blocks);
        self::assertSame(['pdf'], $blocks[0]['values']);
        self::assertNotContains('application/pdf', $blocks[0]['values']);
    }

    private function bind(FileConverterInterface $converter): void
    {
        $this->app->bind(FileConverterInterface::class, static fn () => $converter);
    }

    private function runCommand(array $arguments = []): string
    {
        Artisan::call('filestorage:converter:types:list', $arguments);

        return Artisan::output();
    }

    /**
     * Parses command output into ordered blocks. A block header is any line
     * ending with ':'; subsequent indented lines are the block's values.
     *
     * @return list<array{header: string, values: list<string>}>
     */
    private function parseBlocks(string $output): array
    {
        $blocks = [];
        $current = null;

        foreach (preg_split('/\r?\n/', $output) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            if (str_ends_with($line, ':')) {
                if (null !== $current) {
                    $blocks[] = $current;
                }

                $current = ['header' => mb_substr($line, 0, -1), 'values' => []];
            } elseif (null !== $current) {
                $current['values'][] = $line;
            }
        }

        if (null !== $current) {
            $blocks[] = $current;
        }

        return $blocks;
    }
}
