<?php

namespace App\Services\Storage;

use App\Services\FileConverter\Handlers\FileConverterInterface;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;

class StorageServiceFactory
{
    public function __construct(
        protected FilesystemManager $filesystemManager,
        protected FileConverterInterface $fileConverter,
        protected Repository $config,
    )
    {
    }

    public function getFileStorage(): FileStorageService
    {
        $fileStorageDisk = $this->config->get('filesystems.file_storage', 'local_file_storage');
        $diskConfig = $this->config->get('filesystems.disks.' . $fileStorageDisk);
        $disk = $this->filesystemManager->disk($fileStorageDisk);

        return new FileStorageService(
            $this->config->get('filesystems.upload_limits.allowed_file_mime_types'),
            $this->config->get('filesystems.upload_limits.max_file_size'),
            $diskConfig,
            $disk,
            new UrlGenerator($diskConfig, $disk,),
            $this->fileConverter
        );
    }

    public function getAvatarStorage(): AvatarStorageService
    {
        $avatarDisk = $this->config->get('filesystems.avatar_storage', 'public');
        $diskConfig = $this->config->get('filesystems.disks.' . $avatarDisk);
        $disk = $this->filesystemManager->disk($avatarDisk);

        return new AvatarStorageService(
            $this->config->get('filesystems.upload_limits.allowed_avatar_mime_types'),
            $this->config->get('filesystems.upload_limits.max_avatar_file_size'),
            $diskConfig,
            $disk,
            new UrlGenerator($diskConfig, $disk,),
            $this->fileConverter
        );
    }
}
