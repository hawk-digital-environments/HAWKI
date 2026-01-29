<?php

namespace App\Providers;

use App\Services\ExtApp\ExtAppContext;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\UrlGenerator;
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
                $extAppContext = $app->get(ExtAppContext::class);
                $routeName = $extAppContext->isExternal()
                    ? 'api.external_app.storage.proxy'
                    : 'web.storage.proxy';
                return new UrlGenerator($routeName);
            }
        );
        
        $this->app->singleton(
            AvatarStorageService::class,
            function (Application $app) {
                $config = $app->get(Repository::class);
                $avatarDisk = $config->get('filesystems.avatar_storage', 'public');;
                $diskConfig = $config->get('filesystems.disks.' . $avatarDisk);
                $disk = $app->get('filesystem')->disk($avatarDisk);
                
                return new AvatarStorageService(
                    $config->get('filesystems.upload_limits.allowed_avatar_mime_types'),
                    $config->get('filesystems.upload_limits.max_avatar_file_size'),
                    $diskConfig,
                    $disk,
                    $app->get(UrlGenerator::class)
                );
            }
        );
        
        $this->app->singleton(
            FileStorageService::class,
            function (Application $app) {
                $config = $app->get(Repository::class);
                $fileStorageDisk = $config->get('filesystems.file_storage', 'local_file_storage');
                $diskConfig = $config->get('filesystems.disks.' . $fileStorageDisk);
                $disk = $app->get('filesystem')->disk($fileStorageDisk);
                
                return new FileStorageService(
                    $config->get('filesystems.upload_limits.allowed_file_mime_types'),
                    $config->get('filesystems.upload_limits.max_file_size'),
                    $diskConfig,
                    $disk,
                    $app->get(UrlGenerator::class)
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
