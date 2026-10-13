<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\BrandFonts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BrandFonts::class)]
class BrandFontsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.avatar_storage'));
    }

    public function testItStoresAFontUnderItsContentHashAndMarksVariableFonts(): void
    {
        $static = self::woff2([[0, 0], [10, 0]]);
        $variable = self::woff2([[0, 0], [10, 0], [47, 0]]);
        $fonts = new BrandFonts();

        $stored = $fonts->store(UploadedFile::fake()->createWithContent('Brand.woff2', $static));
        static::assertSame(hash('sha256', $static) . '.woff2', $stored['file']);
        static::assertSame(['Brand.woff2', false], [$stored['name'], $stored['variable']]);
        static::assertSame($static, $fonts->read($stored['file']));

        $stored = $fonts->store(UploadedFile::fake()->createWithContent('Brand-VF.woff2', $variable));
        static::assertSame(hash('sha256', $variable) . '.var.woff2', $stored['file']);
        static::assertTrue(BrandFonts::isVariableFile($stored['file']));
    }

    public function testEveryFormatIsRecognizedByContentAndCheckedForVariationAxes(): void
    {
        // TTF/OTF: 12-byte header and 16-byte records. WOFF: 44-byte header and 20-byte records.
        $sfnt = static fn (string $signature, array $tags): string => $signature . pack('n', \count($tags)) . str_repeat("\0", 6)
            . implode('', array_map(static fn (string $tag) => $tag . str_repeat("\0", 12), $tags));
        $woff = static fn (array $tags): string => 'wOFF' . str_repeat("\0", 8) . pack('n', \count($tags)) . str_repeat("\0", 30)
            . implode('', array_map(static fn (string $tag) => $tag . str_repeat("\0", 16), $tags));
        $fonts = new BrandFonts();

        foreach ([
            ['ttf', $sfnt("\x00\x01\x00\x00", ['cmap', 'glyf']), $sfnt("\x00\x01\x00\x00", ['cmap', 'fvar', 'glyf'])],
            ['otf', $sfnt('OTTO', ['CFF ', 'cmap']), $sfnt('OTTO', ['CFF2', 'fvar'])],
            ['woff', $woff(['cmap', 'glyf']), $woff(['cmap', 'glyf', 'fvar'])],
        ] as [$format, $static, $variable]) {
            // The upload's name is not trusted: the stored extension follows the content.
            $stored = $fonts->store(UploadedFile::fake()->createWithContent('Brand.woff2', $static));
            static::assertSame(hash('sha256', $static) . '.' . $format, $stored['file']);
            static::assertFalse($stored['variable']);
            static::assertSame(hash('sha256', $variable) . '.var.' . $format, $fonts->store(UploadedFile::fake()->createWithContent('Brand.' . $format, $variable))['file']);
            static::assertTrue(BrandFonts::isFile($stored['file']));
        }

        static::assertSame(['truetype', 'font/ttf'], [BrandFonts::cssFormat(str_repeat('a', 64) . '.var.ttf'), BrandFonts::mediaType(str_repeat('a', 64) . '.ttf')]);
        static::assertNull(BrandFonts::format('ttcf collections are not supported'));
    }

    public function testItRejectsFilesThatAreNotFonts(): void
    {
        $this->expectException(ValidationException::class);
        (new BrandFonts())->store(UploadedFile::fake()->createWithContent('Brand.woff2', '<html>not a font</html>'));
    }

    public function testPruneRemovesUnusedFontsOnceTheyAreOlderThanTheGracePeriod(): void
    {
        $disk = Storage::disk(config('filesystems.avatar_storage'));
        $names = ['used' => str_repeat('a', 64) . '.woff2', 'replaced' => str_repeat('b', 64) . '.woff2', 'pending' => str_repeat('c', 64) . '.woff2'];

        foreach ($names as $name) {
            $disk->put('branding/fonts/' . $name, 'wOF2');
        }

        // Everything but the pending upload was stored two days ago.
        touch($disk->path('branding/fonts/' . $names['used']), time() - 2 * 86400);
        touch($disk->path('branding/fonts/' . $names['replaced']), time() - 2 * 86400);
        config(['hawki.branding.font' => $names['used']]);
        $fonts = new BrandFonts();

        static::assertSame(1, $fonts->prune());
        static::assertTrue($fonts->exists($names['used']));
        static::assertFalse($fonts->exists($names['replaced']));
        static::assertTrue($fonts->exists($names['pending']));
        static::assertSame(0, $fonts->prune());
    }

    public function testOnlyStoredHashNamedFilesCanBeRead(): void
    {
        $fonts = new BrandFonts();

        foreach (['../../.env', 'font.woff2', str_repeat('a', 64) . '.woff2', str_repeat('a', 64) . '.woff2/../x'] as $file) {
            static::assertFalse($fonts->exists($file));
            static::assertNull($fonts->read($file));
        }
    }

    public function testVariationAxesAreFoundBehindTransformedAndCustomTables(): void
    {
        // glyf (10) transformed by default and a table with a custom tag both carry extra fields to skip.
        static::assertTrue(BrandFonts::hasVariationAxes(self::woff2([[10, 0], [63, 0, 'ABCD'], [3, 1], [47, 0]])));
        static::assertTrue(BrandFonts::hasVariationAxes(self::woff2([[63, 0, 'fvar']])));
        static::assertFalse(BrandFonts::hasVariationAxes(self::woff2([[10, 0], [11, 0], [48, 0]])));
        static::assertFalse(BrandFonts::hasVariationAxes('not a font'));
    }

    /**
     * A WOFF2 header and table directory without table data, which is all the detection reads.
     *
     * @param list<array{0: int, 1: int, 2?: string}> $tables known-table index, transform version and a custom tag
     */
    private static function woff2(array $tables): string
    {
        $directory = '';

        foreach ($tables as $table) {
            [$index, $version] = $table;
            $directory .= \chr($version << 6 | $index) . ($table[2] ?? '');
            // A two-byte length, then a transform length where the table is transformed.
            $directory .= "\x81\x00";
            $transformed = \in_array($index, [10, 11], true) ? 0 === $version : 0 !== $version;
            $directory .= $transformed ? "\x81\x00" : '';
        }

        return 'wOF2' . str_repeat("\0", 8) . pack('n', \count($tables)) . str_repeat("\0", 34) . $directory;
    }
}
