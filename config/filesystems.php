<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => config('filesystems.default', env('FILESYSTEM_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => config('app.url').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => config('filesystems.disks.s3.key', env('AWS_ACCESS_KEY_ID')),
            'secret' => config('filesystems.disks.s3.secret', env('AWS_SECRET_ACCESS_KEY')),
            'region' => config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION')),
            'bucket' => config('filesystems.disks.s3.bucket', env('AWS_BUCKET')),
            'url' => config('filesystems.disks.s3.url', env('AWS_URL')),
            'endpoint' => config('filesystems.disks.s3.endpoint', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
