<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Files administrators upload for the branding of the interface, such as fonts or the sign-in
 * background. They are needed on the sign-in page, so they are served publicly, and on every
 * instance of a deployment, so they share the disk of the avatars.
 *
 * A file is named after its content hash, so it never changes once stored and can be cached
 * forever. Files no setting uses any more are removed by {@see self::prune()}.
 */
abstract class BrandAssets
{
    /** How long an uploaded file may wait for its setting to be saved before cleanup removes it. */
    public const GRACE_HOURS = 24;

    /** Directory of this kind of asset on the disk, with a trailing slash. */
    abstract protected function directory(): string;

    /** Whether a name is one this kind of asset can be stored under. */
    abstract public static function isFile(mixed $file): bool;

    /** @return list<mixed> the files the current settings use */
    abstract protected function used(): array;

    public function exists(string $file): bool
    {
        return static::isFile($file) && $this->disk()->exists($this->directory() . $file);
    }

    public function read(string $file): ?string
    {
        return $this->exists($file) ? $this->disk()->get($this->directory() . $file) : null;
    }

    /**
     * Deletes stored files that no setting uses any more and returns how many were removed.
     *
     * Files younger than {@see self::GRACE_HOURS} are kept: an upload is stored before its setting
     * is saved, so a fresh file may still be waiting in an administrator's unsaved changes.
     * Cleanup never fails the operation that triggered it.
     */
    public function prune(): int
    {
        $used = $this->used();
        $deleted = 0;

        try {
            foreach ($this->disk()->files($this->directory()) as $path) {
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

    /**
     * The HTTP response delivering a stored file, or null when there is none under that name.
     * On a local disk it answers byte-range requests, which Safari needs to play videos.
     */
    public function response(string $file, string $mediaType): ?Response
    {
        if (!$this->exists($file)) {
            return null;
        }

        // The name is the content hash, so the file behind a URL never changes.
        $headers = [
            'Content-Type' => $mediaType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $path = $this->directory() . $file;

        if ('local' === config('filesystems.disks.' . config('filesystems.avatar_storage') . '.driver')) {
            return response()->file($this->disk()->path($path), $headers);
        }

        return $this->disk()->response($path, $file, $headers);
    }

    protected function put(string $file, string $bytes): void
    {
        $this->disk()->put($this->directory() . $file, $bytes);
        // Abandoned uploads are cleared whenever someone uploads again, not only when a setting is saved.
        $this->prune();
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.avatar_storage'));
    }
}
