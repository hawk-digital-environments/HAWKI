---
sidebar_position: 7
---

# File Storage System

HAWKI's file storage system provides a unified interface for storing, retrieving, and managing files across multiple storage drivers. Built on Laravel's filesystem abstraction, it supports local storage, cloud services (S3), SFTP, and WebDAV with automatic URL generation and cleanup functionality.

## Architecture Overview

The system uses a factory pattern combined with Laravel's singleton pattern to provide consistent file operations across different storage backends:

- **Storage Services**: `FileStorageService` (general files) and `AvatarStorageService` (profile images)
- **Factory Pattern**: `StorageServiceFactory` creates configured service instances
- **URL Generation**: Driver-aware URL generation with security features
- **Automatic Cleanup**: Temporary file expiration and garbage collection

### File Organization Structure

Files are organized using UUID-based directory sharding for scalability:

```
{category}/
├── {1st_char_of_uuid}/
│   ├── {2nd_char_of_uuid}/
│   │   ├── {3rd_char_of_uuid}/
│   │   │   ├── {4th_char_of_uuid}/
│   │   │   │   └── {uuid}/
│   │   │   │       ├── {uuid}.{extension}
│   │   │   │       └── subdirectories/
```

Temporary files include a `temp/` prefix: `temp/{category}/{uuid_path}/{uuid}/`

## Supported Storage Drivers

### Local Storage
```php
'local_file_storage' => [
    'driver' => 'local',
    'root' => storage_path('app/data_repo'),
    'visibility' => 'private',
]
```
**Use Case**: Development, single-server deployments  
**Access**: Through application routes only

### Public Local Storage
```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
]
```
**Use Case**: Avatar images, publicly accessible assets  
**Access**: Direct web access via `php artisan storage:link`

### Amazon S3
```php
's3' => [
    'driver' => 's3',
    'key' => env('S3_ACCESS_KEY'),
    'secret' => env('S3_SECRET_KEY'),
    'region' => env('S3_REGION'),
    'bucket' => env('S3_DEFAULT_BUCKET'),
    'endpoint' => env('S3_ENDPOINT'),
    'visibility' => 'private',
]
```

### Nextcloud (WebDAV)
```php
'nextcloud' => [
    'driver' => 'webdav',
    'base_uri' => env('NEXTCLOUD_BASE_URL') . '/remote.php/dav/files/' . env('NEXTCLOUD_USERNAME') . '/',
    'username' => env('NEXTCLOUD_USERNAME'),
    'password' => env('NEXTCLOUD_PASSWORD'),
]
```

### SFTP
```php
'sftp' => [
    'driver' => 'sftp',
    'host' => env('SFTP_HOST'),
    'port' => env('SFTP_PORT', 22),
    'username' => env('SFTP_USERNAME'),
    'password' => env('SFTP_PASSWORD'),
]
```

## Core API

### StorageServiceInterface

All storage services implement this interface:

#### store()
```php
public function store(
    UploadedFile|string $file, 
    string $filename, 
    string $uuid, 
    string $category, 
    bool $temp = false,
    string $subDir = ''
): bool
```

Store a file with UUID-based organization. Set `$temp = true` for temporary storage.

#### retrieve()
```php
public function retrieve(string $uuid, string $category): ?string
```

Retrieve file contents. Returns `null` if file doesn't exist.

#### getUrl()
```php
public function getUrl(string $uuid, string $category): ?string
```

Generate access URL using driver-appropriate method (direct URLs, pre-signed URLs, or signed routes).

#### delete()
```php
public function delete(string $uuid, string $category): bool
```

Delete file and associated directories.

#### moveFileToPersistentFolder()
```php
public function moveFileToPersistentFolder(string $uuid, string $category): bool
```

Move files from temporary to permanent storage.

## URL Generation System

The system automatically selects optimal URL generation based on storage configuration:

| Driver      | Visibility | URL Method               | Result                  |
|-------------|------------|--------------------------|-------------------------|
| local       | public     | `disk->url()`            | Direct web URLs         |
| local       | private    | `temporarySignedRoute()` | Laravel signed routes   |
| s3          | private    | `temporaryUrl()`         | S3 pre-signed URLs      |
| sftp/webdav | private    | `temporarySignedRoute()` | Proxied through Laravel |

### URL Examples

**Direct Access (Local Public)**:
```
https://yourapp.com/storage/avatars/a/b/c/d/abcd-1234/abcd-1234.jpg
```

**Signed Route (Private)**:
```
https://yourapp.com/files/documents/download/uuid-123?expires=1640995200&signature=abc123...
```

**S3 Pre-signed**:
```
https://bucket.s3.amazonaws.com/path/file.jpg?X-Amz-Expires=86400&X-Amz-Signature=...
```

## Service Usage

### Dependency Injection
```php
class DocumentController extends Controller
{
    public function upload(Request $request, FileStorageService $storage)
    {
        $file = $request->file('document');
        $uuid = Str::uuid();
        
        $stored = $storage->store(
            file: $file,
            filename: $file->getClientOriginalName(),
            uuid: $uuid,
            category: 'documents'
        );
        
        if ($stored) {
            return response()->json([
                'uuid' => $uuid,
                'download_url' => $storage->getUrl($uuid, 'documents')
            ]);
        }
        
        return response()->json(['error' => 'Upload failed'], 500);
    }
}
```

### Avatar Management
```php
public function uploadAvatar(Request $request, AvatarStorageService $avatarStorage)
{
    $avatar = $request->file('avatar');
    $avatarId = Str::uuid();
    
    $stored = $avatarStorage->store(
        file: $avatar,
        filename: $avatarId . '.' . $avatar->getClientOriginalExtension(),
        uuid: $avatarId,
        category: 'profile_avatars'
    );
    
    if ($stored) {
        return response()->json([
            'avatar_url' => $avatarStorage->getUrl($avatarId, 'profile_avatars')
        ]);
    }
}
```

### Temporary Files Workflow
```php
// 1. Upload to temporary storage
$stored = $storage->store($file, $filename, $uuid, 'documents', temp: true);

// 2. Later, move to permanent storage
$moved = $storage->moveFileToPersistentFolder($uuid, 'documents');
```

## Configuration

### Environment Variables

```bash
# Storage Configuration
FILESYSTEM_DISK=local
STORAGE_DISK=local_file_storage
AVATAR_STORAGE=public
REMOVE_FILES_AFTER_MONTHS=6

# S3 Configuration (if using S3)
S3_ACCESS_KEY=your_access_key
S3_SECRET_KEY=your_secret_key
S3_REGION=us-east-1
S3_DEFAULT_BUCKET=your-bucket-name
S3_ENDPOINT=https://s3.amazonaws.com

# Nextcloud Configuration (if using Nextcloud)
NEXTCLOUD_BASE_URL=https://your-nextcloud.com
NEXTCLOUD_USERNAME=your-username
NEXTCLOUD_PASSWORD=your-app-password
NEXTCLOUD_BASE_PATH=HAWKI-Files

# SFTP Configuration (if using SFTP)
SFTP_HOST=your-sftp-server.com
SFTP_USERNAME=your-username
SFTP_PASSWORD=your-password
SFTP_BASE_PATH=/home/user/uploads
```

### Service Registration

Services are registered as singletons in `AppServiceProvider`:

```php
public function register()
{
    $this->app->singleton(FileStorageService::class, function ($app) {
        return $app->make(StorageServiceFactory::class)->getFileStorage();
    });

    $this->app->singleton(AvatarStorageService::class, function ($app) {
        return $app->make(StorageServiceFactory::class)->getAvatarStorage();
    });
}
```

## Adding New Storage Drivers

### 1. Create Driver Configuration

Add to `config/filesystems.php`:

```php
'disks' => [
    'my_custom_driver' => [
        'driver' => 'my_driver',
        'custom_setting' => env('MY_DRIVER_SETTING'),
        'visibility' => 'private',
    ]
]
```

### 2. Register Custom Driver (if needed)

If using a custom driver not supported by Laravel:

```php
// In AppServiceProvider boot()
Storage::extend('my_driver', function ($app, $config) {
    return new Filesystem(new MyCustomAdapter($config));
});
```

### 3. Update URL Generation (if needed)

Extend the `UrlGenerator` trait if your driver needs special URL handling:

```php
class CustomFileStorage extends AbstractFileStorage
{
    protected function generateUrl(string $path, string $uuid, string $category): string
    {
        if ($this->config['driver'] === 'my_driver') {
            return $this->generateCustomUrl($path, $uuid, $category);
        }
        
        return parent::generateUrl($path, $uuid, $category);
    }
    
    private function generateCustomUrl(string $path, string $uuid, string $category): string
    {
        // Custom URL generation logic
        return "https://my-service.com/files/{$uuid}";
    }
}
```

### 4. Create Custom Service

```php
class CustomStorageService extends AbstractFileStorage
{
    public function __construct(array $config, Filesystem $disk)
    {
        parent::__construct($config, $disk);
    }
    
    // Override methods if needed for driver-specific behavior
}
```

### 5. Update Factory

```php
class StorageServiceFactory
{
    public function getCustomStorage(): CustomStorageService
    {
        $disk = $this->config->get('filesystems.my_custom_storage', 'my_custom_driver');
        return new CustomStorageService(
            config('filesystems.disks.' . $disk),
            $this->filesystemManager->disk($disk)
        );
    }
}
```

## File Cleanup and Management

### Automatic Cleanup

The system includes automatic cleanup for temporary files:

```php
// Clean up expired temporary files (older than 5 minutes)
$cleaned = $storage->deleteTempExpiredFiles();
```

### Scheduled Cleanup Command

```php
class CleanupExpiredFiles extends Command
{
    public function handle(FileStorageService $storage)
    {
        $cleaned = $storage->deleteTempExpiredFiles();
        $this->info($cleaned ? 'Temporary files cleaned up' : 'No expired files found');
    }
}
```

### Configuration-based Cleanup

Files are automatically cleaned based on configuration:

```php
'garbage_collections' => [
    'remove_files_after_months' => env('REMOVE_FILES_AFTER_MONTHS', 6),
]
```

## Error Handling

### Exception Types
- `FileNotFoundException`: File doesn't exist
- `Storage exceptions`: Various filesystem-related errors

### Error Handling Pattern
```php
try {
    $content = $storage->retrieve($uuid, 'documents');
    if (!$content) {
        return response()->json(['error' => 'File not found'], 404);
    }
    // Process content
} catch (FileNotFoundException $e) {
    return response()->json(['error' => 'File not found'], 404);
} catch (Exception $e) {
    Log::error('Storage operation failed', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'Storage operation failed'], 500);
}
```

## Security Features

- **Base64 Path Encoding**: Prevents path traversal attacks
- **Temporary URL Expiration**: Default 24-hour expiration
- **Signed Routes**: Laravel signature verification for private files
- **Driver-appropriate Security**: Uses each driver's native security features

## Performance Considerations

- **Direct Access**: Public files served directly by web server (fastest)
- **Cloud Pre-signed URLs**: S3 files served directly from cloud (fast)
- **Proxied Access**: Private files served through Laravel (controlled but slower)
- **Singleton Pattern**: Services instantiated once per request for efficiency

This file storage system provides a robust, scalable solution for file management in HAWKI while maintaining security and performance across different storage backends.
