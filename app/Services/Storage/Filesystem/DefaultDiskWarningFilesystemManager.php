<?php

declare(strict_types=1);

namespace App\Services\Storage\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Psr\Log\LoggerInterface;

/**
 * Drop-in replacement for the framework FilesystemManager that turns implicit default-disk
 * usage into a visible warning.
 *
 * HAWKI never uses the framework default disk (`filesystems.default`) as storage — the storage
 * services resolve their disks explicitly via the `filesystems.file_storage` /
 * `filesystems.avatar_storage` roles. Code that resolves a disk without an explicit name
 * (e.g. a bare `Storage::put(...)` or `app('filesystem')->disk()`) is almost certainly a
 * bug or an uninformed third-party package, so it logs a warning once per request while
 * still returning the disk (warn-and-continue: the default remains a valid framework fallback).
 */
class DefaultDiskWarningFilesystemManager extends FilesystemManager
{
    private bool $defaultDiskWarningEmitted = false;

    public function __construct(Application $app, private readonly LoggerInterface $logger)
    {
        parent::__construct($app);
    }

    /**
     * @inheritDoc
     */
    public function disk($name = null): Filesystem
    {
        if ($name === null || $name === '') {
            $this->warnAboutDefaultDiskFallback();
        }

        return parent::disk($name);
    }

    /**
     * Emit the fallback warning once per request — repeated default-disk resolutions
     * within the same request would otherwise flood the log.
     */
    private function warnAboutDefaultDiskFallback(): void
    {
        if ($this->defaultDiskWarningEmitted) {
            return;
        }

        $this->defaultDiskWarningEmitted = true;

        $this->logger->warning(
            'The framework default filesystem disk was resolved without an explicit disk name. '
            . 'HAWKI never uses the default disk as storage — resolve disks explicitly via '
            . 'filesystems.file_storage / filesystems.avatar_storage instead.'
        );
    }
}
