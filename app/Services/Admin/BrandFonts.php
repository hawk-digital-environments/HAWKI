<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores the font file administrators upload for the interface and tells how to declare it.
 *
 * A file is named after its content hash, so it never changes once stored and can be cached
 * forever. WOFF2, WOFF, TTF and OTF are accepted and served as uploaded. Fonts no setting uses any more are removed by {@see self::prune()}. Variable fonts carry `.var` in their name: one such file covers every weight, which the
 * `@font-face` rules of {@see \App\Services\Frontend\View\BrandTheme} and the admin preview need to know.
 */
class BrandFonts
{
    public const MAX_KILOBYTES = 5120;

    /**
     * The accepted formats by file extension: the name browsers expect in `format()` and the media type.
     * A file's format is read from its first bytes, never from the name it was uploaded under.
     */
    public const FORMATS = [
        'woff2' => ['woff2', 'font/woff2'],
        'woff' => ['woff', 'font/woff'],
        'ttf' => ['truetype', 'font/ttf'],
        'otf' => ['opentype', 'font/otf'],
    ];

    /** A stored font file: content hash, `.var` for a variable font, and the format as extension. */
    public const FILE = '/^[a-f0-9]{64}(?:\.var)?\.(?:woff2|woff|ttf|otf)$/D';

    /** How long an uploaded file may wait for its setting to be saved before cleanup removes it. */
    public const GRACE_HOURS = 24;

    private const DIRECTORY = 'branding/fonts/';

    public static function isFile(mixed $file): bool
    {
        return \is_string($file) && 1 === preg_match(self::FILE, $file);
    }

    public static function isVariableFile(string $file): bool
    {
        return str_contains($file, '.var.');
    }

    /** The `format()` name of a stored file for its `@font-face` rule. */
    public static function cssFormat(string $file): string
    {
        return self::FORMATS[pathinfo($file, \PATHINFO_EXTENSION)][0];
    }

    /** The media type a stored file is served with. */
    public static function mediaType(string $file): string
    {
        return self::FORMATS[pathinfo($file, \PATHINFO_EXTENSION)][1];
    }

    /** The format of font data, as one of the keys of {@see self::FORMATS}, or null when it is none of them. */
    public static function format(string $bytes): ?string
    {
        return match (substr($bytes, 0, 4)) {
            'wOF2' => 'woff2',
            'wOFF' => 'woff',
            'OTTO' => 'otf',
            "\x00\x01\x00\x00", 'true' => 'ttf',
            default => null,
        };
    }

    public function uploadRules(): array
    {
        return ['required', 'file', 'max:' . self::MAX_KILOBYTES, 'extensions:' . implode(',', array_keys(self::FORMATS))];
    }

    /**
     * @return array{file: string, name: string, variable: bool}
     */
    public function store(UploadedFile $upload): array
    {
        $bytes = $upload->getContent();

        $format = self::format($bytes) ?? throw ValidationException::withMessages(['font' => __('admin.fonts.invalid')]);
        $variable = self::hasVariationAxes($bytes);
        $file = hash('sha256', $bytes) . ($variable ? '.var' : '') . '.' . $format;
        $this->disk()->put(self::DIRECTORY . $file, $bytes);
        // Abandoned uploads are cleared whenever someone uploads again, not only when a font is saved.
        $this->prune();

        return ['file' => $file, 'name' => mb_substr($upload->getClientOriginalName(), 0, 255), 'variable' => $variable];
    }

    /**
     * Deletes stored fonts that no setting uses any more and returns how many were removed.
     *
     * Files younger than {@see self::GRACE_HOURS} are kept: an upload is stored before its setting
     * is saved, so a fresh file may still be waiting in an administrator's unsaved changes.
     * Cleanup never fails the operation that triggered it.
     */
    public function prune(): int
    {
        $used = [config('hawki.branding.font')];
        $deleted = 0;

        try {
            foreach ($this->disk()->files(self::DIRECTORY) as $path) {
                if (\in_array(basename($path), $used, true)
                    || $this->disk()->lastModified($path) > now()->subHours(self::GRACE_HOURS)->getTimestamp()) {
                    continue;
                }

                $this->disk()->delete($path);
                ++$deleted;
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $deleted;
    }

    public function exists(string $file): bool
    {
        return self::isFile($file) && $this->disk()->exists(self::DIRECTORY . $file);
    }

    public function read(string $file): ?string
    {
        return $this->exists($file) ? $this->disk()->get(self::DIRECTORY . $file) : null;
    }

    /**
     * Whether font data is a variable font, i.e. lists an `fvar` table in its table directory.
     */
    public static function hasVariationAxes(string $bytes): bool
    {
        return match (self::format($bytes)) {
            'woff2' => self::woff2HasVariationAxes($bytes),
            // WOFF: a 44-byte header, then 20-byte records. TTF/OTF: a 12-byte header, then 16-byte records.
            'woff' => self::directoryListsVariationAxes($bytes, 12, 44, 20),
            'ttf', 'otf' => self::directoryListsVariationAxes($bytes, 4, 12, 16),
            default => false,
        };
    }

    /**
     * Reads a table directory whose records start with the table's four-letter tag.
     */
    private static function directoryListsVariationAxes(string $bytes, int $countOffset, int $start, int $recordLength): bool
    {
        if (\strlen($bytes) < $start) {
            return false;
        }

        $tables = unpack('n', $bytes, $countOffset)[1];

        for ($i = 0; $i < $tables; ++$i) {
            if ('fvar' === substr($bytes, $start + $i * $recordLength, 4)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The WOFF2 directory is not compressed, but packed: known tables are stored as an index,
     * `fvar` being number 47, and every record has a variable length.
     */
    private static function woff2HasVariationAxes(string $bytes): bool
    {
        $length = \strlen($bytes);

        if ($length < 48) {
            return false;
        }

        $tables = unpack('n', $bytes, 12)[1];
        $offset = 48;
        $skipNumber = static function () use ($bytes, $length, &$offset): void {
            // UIntBase128: up to five bytes, the high bit marks a following byte.
            for ($i = 0; $i < 5 && $offset < $length; ++$i) {
                if (0 === (\ord($bytes[$offset++]) & 0x80)) {
                    return;
                }
            }
        };

        for ($i = 0; $i < $tables && $offset < $length; ++$i) {
            $flags = \ord($bytes[$offset++]);
            $index = $flags & 0x3F;
            $tag = null;

            if (63 === $index) {
                $tag = substr($bytes, $offset, 4);
                $offset += 4;
            }

            if (47 === $index || 'fvar' === $tag) {
                return true;
            }

            $skipNumber();
            // glyf (10) and loca (11) are transformed by default (version 0), every other table only when flagged.
            $transformed = \in_array($index, [10, 11], true) ? 0 === $flags >> 6 : 0 !== $flags >> 6;

            if ($transformed) {
                $skipNumber();
            }
        }

        return false;
    }

    /**
     * Fonts are needed on the sign-in page, on every instance of a deployment: they share the disk of the avatars.
     */
    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.avatar_storage'));
    }
}
