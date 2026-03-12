<?php

use Spatie\Backup\BackupServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\RoutingServiceProvider::class,
    \App\Providers\ToolServiceProvider::class,
    ...(
    class_exists(BackupServiceProvider::class) && getenv('BACKUP_DISABLED') === false
        ? [BackupServiceProvider::class] :
        []),
];
