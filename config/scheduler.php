<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled Tasks Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior and activation of background tasks.
    |
    */

    'model_status_check' => [
        'enabled' => env('SCHEDULE_MODEL_STATUS_CHECK_ENABLED', true),
    ],

    'filestorage_cleanup' => [
        'enabled' => env('SCHEDULE_FILESTORAGE_CLEANUP_ENABLED', true),
    ],

    'backup' => [
        'enabled' => env('BACKUP_ENABLED', true),
        'schedule_interval' => env('BACKUP_INTERVAL', 'daily'),
        'schedule_time' => env('BACKUP_TIME', '02:00'),
        'include_files' => env('BACKUP_INCLUDE_FILES', false),
    ],

    'cleanup' => [
        'enabled' => env('BACKUP_CLEANUP_ENABLED', true),
    ],

];
