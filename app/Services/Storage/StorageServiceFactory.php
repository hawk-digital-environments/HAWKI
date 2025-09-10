<?php

namespace App\Services\Storage;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;

class StorageServiceFactory
{
    public function __construct(
        protected FilesystemManager $filesystemManager,
        protected Repository $config
    )
    {
    }

    public function getFileStorage(): FileStorageService
    {
        $fileStorageDisk = $this->config->get('filesystems.file_storage', 'local_file_storage');
        return new FileStorageService(config('filesystems.disks.' . $fileStorageDisk),
                                      $this->filesystemManager->disk($fileStorageDisk));
    }

    public function getAvatarStorage(): AvatarStorageService
    {
        $avatarDisk = $this->config->get('filesystems.avatar_storage', 'public');
        return new AvatarStorageService(config('filesystems.disks.' . $avatarDisk),
                                        $this->filesystemManager->disk($avatarDisk));
    }
}
