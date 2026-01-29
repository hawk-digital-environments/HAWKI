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
    | default filesystem handles the main system files
    | storage handles the data repo for storing large and numerous files such as user uploaded files.
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'file_storage' => env('STORAGE_DISK', 'local_file_storage'),

    'avatar_storage' => env('AVATAR_STORAGE', 'public'),
    
    /*
    |--------------------------------------------------------------------------
    | Filesystem Upload limits
    |--------------------------------------------------------------------------
    |
    | Here you may specify the upload limits for different types of files.
    | These limits are used to validate file uploads throughout the application.
    */
    
    'upload_limits' => [
        // The maximum file size for an uploaded file(e.g. attachment) in bytes - default is 20 MB
        'max_file_size' => env('MAX_FILE_SIZE', 10 * 1024 * 1024),
        // The maximum file size for an avatar in bytes - default is 2 MB
        'max_avatar_file_size' => env('MAX_AVATAR_FILE_SIZE', 2 * 1024 * 1024),
        // Allowed MIME types for uploaded files(e.g. attachments) - comma separated list in .env (If empty, the defaults are defined in the file storage service)
        'allowed_file_mime_types' => array_values(array_filter(explode(',', env('ALLOWED_FILE_MIME_TYPES', '')))),
        // Allowed MIME types for uploaded avatars - comma separated list in .env (If empty, the defaults are defined in the avatar storage service)
        'allowed_avatar_mime_types' => array_values(array_filter(explode(',', env('ALLOWED_AVATAR_MIME_TYPES', '')))),
        // Maximum number of files that can be attached to a single message (0 = unlimited)
        'max_attachment_files' => env('MAX_ATTACHMENT_FILES', 0),
    ],

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
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'local_file_storage' => [
            'driver' => 'local',
            'root' => storage_path('app/data_repo'),
            'url' => env('APP_URL').'/data_repo',
            'visibility' => 'private',
            'serve' => true,
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('S3_ACCESS_KEY'),
            'secret' => env('S3_SECRET_KEY'),
            'region' => env('S3_REGION'),
            'bucket' => env('S3_DEFAULT_BUCKET'),
            'endpoint' => env('S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ],

        'nextcloud' => [
            'driver' => 'webdav',
            'base_uri' => env('NEXTCLOUD_BASE_URL') . '/remote.php/dav/files/' . env('NEXTCLOUD_USERNAME') . '/',
            'username' => env('NEXTCLOUD_USERNAME'),
            'password' => env('NEXTCLOUD_PASSWORD'),
            'prefix' => env('NEXTCLOUD_BASE_PATH', ''),
            'timeout' => 60,
            'visibility' => 'private',

        ],

        'sftp' => [
            'driver' => 'sftp',
            'host' => env('SFTP_HOST'),
            'port' => (int) env('SFTP_PORT', 22),
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),
            'root' => env('SFTP_BASE_PATH', '/'),
            'timeout' => 30,
            'visibility' => 'private',

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


    'garbage_collections' => [
        'remove_files_after_months' => env('REMOVE_FILES_AFTER_MONTHS', 6),
    ]

];
