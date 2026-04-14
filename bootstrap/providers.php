<?php

declare(strict_types=1);

use Spatie\Backup\BackupServiceProvider;

$backupEnabled = class_exists(BackupServiceProvider::class) && getenv('BACKUP_DISABLED') === false;

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\RoutingServiceProvider::class,
    App\Providers\TranslationServiceProvider::class,
    App\Providers\ToolServiceProvider::class,
    App\Providers\FrontendServiceProvider::class,
    App\Providers\StorageServiceProvider::class,
    ...($backupEnabled ? [BackupServiceProvider::class] : []),
    App\Providers\FileConverterServiceProvider::class,
];
