<?php

namespace App\Providers;

use App\Services\Chat\Attachment\Db\AttachmentDb;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\UrlGenerator;
use App\Services\Storage\Utils\ContentExtractor;
use App\Services\Storage\Value\StorageServiceContext;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Sabre\DAV\Client;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            UrlGenerator::class,
            function (Application $app) {
                return new UrlGenerator('web.storage.proxy');
            }
        );

        $this->app->singleton(
            AvatarStorageService::class,
            function (Application $app) {
                $config = $app->get(Repository::class);
                $avatarDisk = $config->get('filesystems.avatar_storage', 'public');
                $filesystem = $app->get('filesystem')->disk($avatarDisk);

                return new AvatarStorageService(
                    context: new StorageServiceContext(
                        allowedMimeTypes: $config->get('filesystems.upload_limits.allowed_avatar_mime_types'),
                        maxFileSize: $config->get('filesystems.upload_limits.max_avatar_file_size'),
                        logger: $app->get('log'),
                        filesystem: $filesystem,
                        urlGenerator: $app->get(UrlGenerator::class),
                        contentExtractor: $app->get(ContentExtractor::class),
                        attachmentDb: $app->get(AttachmentDb::class)
                    )
                );
            }
        );

        $this->app->singleton(
            FileStorageService::class,
            function (Application $app) {
                $config = $app->get(Repository::class);
                $fileStorageDisk = $config->get('filesystems.file_storage', 'local_file_storage');
                $filesystem = $app->get('filesystem')->disk($fileStorageDisk);

                return new FileStorageService(
                    context: new StorageServiceContext(
                        allowedMimeTypes: $config->get('filesystems.upload_limits.allowed_file_mime_types'),
                        maxFileSize: $config->get('filesystems.upload_limits.max_file_size'),
                        logger: $app->get('log'),
                        filesystem: $filesystem,
                        urlGenerator: $app->get(UrlGenerator::class),
                        contentExtractor: $app->get(ContentExtractor::class),
                        attachmentDb: $app->get(AttachmentDb::class)
                    ),
                    fileConverter: $this->app->get(FileConverterInterface::class)
                );
            }
        );
    }

    public function boot(): void
    {
        // Register WebDAV driver for NextCloud support
        Storage::extend('webdav', static function ($app, $config) {
            $client = new Client([
                'baseUri' => $config['base_uri'],
                'userName' => $config['username'],
                'password' => $config['password'],
            ]);

            $adapter = new WebDAVAdapter($client, $config['prefix'] ?? '');

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }
}
