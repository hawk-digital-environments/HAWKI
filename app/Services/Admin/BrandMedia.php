<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Stores the image or video administrators upload as the background of the sign-in page.
 *
 * Accepted are JPEG, PNG, WebP and AVIF images and MP4 and WebM videos; the format is read from a
 * file's first bytes, never from the name it was uploaded under. SVG is not accepted: the file is
 * served from the application's own origin.
 */
class BrandMedia extends BrandAssets
{
    public const MAX_KILOBYTES = 51200;

    /** The accepted formats by file extension: media type, and whether it is an image or a video. */
    public const FORMATS = [
        'jpg' => ['image/jpeg', 'image'],
        'png' => ['image/png', 'image'],
        'webp' => ['image/webp', 'image'],
        'avif' => ['image/avif', 'image'],
        'mp4' => ['video/mp4', 'video'],
        'webm' => ['video/webm', 'video'],
    ];

    /** A stored file: content hash and the format as extension. */
    public const FILE = '/^[a-f0-9]{64}\.(?:jpg|png|webp|avif|mp4|webm)$/D';

    public static function isFile(mixed $file): bool
    {
        return \is_string($file) && 1 === preg_match(self::FILE, $file);
    }

    public static function mediaType(string $file): string
    {
        return self::FORMATS[pathinfo($file, \PATHINFO_EXTENSION)][0];
    }

    /** Whether a stored file is an `image` or a `video`. */
    public static function kind(string $file): string
    {
        return self::FORMATS[pathinfo($file, \PATHINFO_EXTENSION)][1];
    }

    /** The format of file data, as one of the keys of {@see self::FORMATS}, or null when it is none of them. */
    public static function format(string $bytes): ?string
    {
        // ISO media (MP4, AVIF) names its kind in the brand after the `ftyp` box at byte 4.
        $brand = 'ftyp' === substr($bytes, 4, 4) ? substr($bytes, 8, 4) : null;

        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($bytes, 'RIFF') && 'WEBP' === substr($bytes, 8, 4) => 'webp',
            str_starts_with($bytes, "\x1A\x45\xDF\xA3") => 'webm',
            \in_array($brand, ['avif', 'avis'], true) => 'avif',
            // HEIC/HEIF is ISO media too, but browsers outside Safari cannot show it.
            null !== $brand && !\in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true) => 'mp4',
            default => null,
        };
    }

    public function uploadRules(): array
    {
        return ['required', 'file', 'max:' . self::MAX_KILOBYTES, 'extensions:' . implode(',', [...array_keys(self::FORMATS), 'jpeg', 'm4v'])];
    }

    /**
     * @return array{file: string, name: string, kind: string}
     */
    public function store(UploadedFile $upload): array
    {
        $bytes = $upload->getContent();
        $format = self::format($bytes) ?? throw ValidationException::withMessages(['media' => __('admin.media.invalid')]);
        $file = hash('sha256', $bytes) . '.' . $format;
        $this->put($file, $bytes);

        return ['file' => $file, 'name' => mb_substr($upload->getClientOriginalName(), 0, 255), 'kind' => self::FORMATS[$format][1]];
    }

    protected function directory(): string
    {
        return 'branding/media/';
    }

    protected function used(): array
    {
        return array_column(self::backgrounds(config('hawki.branding.login_backgrounds')), 'file');
    }

    /**
     * Checks a list of sign-in backgrounds and returns it in its canonical shape; entries whose file
     * does not exist are dropped. With `$strict`, an invalid entry is an error instead.
     *
     * An entry is `source` (`upload`, a file stored here, or `builtin`, a file in `public/bg_videos`),
     * `file`, and an optional `credit` with an optional `link`.
     *
     * @return list<array{source: string, file: string, credit: string|null, link: string|null}>
     */
    public static function backgrounds(mixed $list, bool $strict = false): array
    {
        $entries = [];
        $invalid = static fn () => $strict ? throw ValidationException::withMessages(['value' => __('admin.media.invalid_entry')]) : null;

        foreach (\is_array($list) ? array_values($list) : [] as $entry) {
            $source = $entry['source'] ?? null;
            $file = $entry['file'] ?? null;
            $credit = $entry['credit'] ?? null;
            $link = $entry['link'] ?? null;
            $exists = match ($source) {
                'upload' => app(self::class)->exists((string) $file),
                'builtin' => self::isBuiltinFile($file) && is_file(public_path('bg_videos/' . $file)),
                default => false,
            };

            if (!$exists
                || (null !== $credit && (!\is_string($credit) || mb_strlen($credit) > 100))
                || (null !== $link && '' !== $link && 1 !== preg_match('~^https?://[^\s"\'<>]+$~', (string) $link))) {
                $invalid();

                continue;
            }

            $entries[] = ['source' => $source, 'file' => $file,
                'credit' => '' === trim((string) $credit) ? null : trim($credit), 'link' => '' === (string) $link ? null : $link];
        }

        return $entries;
    }

    /** A file of the built-in backgrounds in `public/bg_videos`: a plain name with a known extension. */
    public static function isBuiltinFile(mixed $file): bool
    {
        return \is_string($file) && 1 === preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,100}\.(?:jpg|png|webp|avif|mp4|webm)$/D', $file);
    }

    /** The kind of a background by its extension: `image` or `video`. */
    public static function kindOf(string $file): string
    {
        return \in_array(pathinfo($file, \PATHINFO_EXTENSION), ['mp4', 'webm'], true) ? 'video' : 'image';
    }
}
