<?php

namespace App\Services\Storage;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;

class StorageServiceFactory
{
    public function __construct(
        protected FilesystemManager $filesystemManager,
        protected Repository $config,
    )
    {
    }

    public function getFileStorage(): FileStorageService
    {
        $fileStorageDisk = $this->config->get('filesystems.file_storage', 'local_file_storage');
        $diskConfig = $this->config->get('filesystems.disks.' . $fileStorageDisk);
        $disk = $this->filesystemManager->disk($fileStorageDisk);

        return new FileStorageService($diskConfig, $disk,
                                      new UrlGenerator($diskConfig,$disk,));
    }

    public function getAvatarStorage(): AvatarStorageService
    {
        $avatarDisk = $this->config->get('filesystems.avatar_storage', 'public');
        $diskConfig = $this->config->get('filesystems.disks.' . $avatarDisk);
        $disk = $this->filesystemManager->disk($avatarDisk);

        return new AvatarStorageService($diskConfig, $disk,
                                        new UrlGenerator($diskConfig,$disk,));
    }
}
