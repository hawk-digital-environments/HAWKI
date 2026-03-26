---
sidebar_position: 7
---

# File Storage System

HAWKI's file storage system provides a unified interface for storing, retrieving, and managing files across multiple storage drivers. Built on Laravel's filesystem abstraction, it supports local storage, cloud services (S3), SFTP, and WebDAV with automatic URL generation, content extraction, and cleanup functionality.

## Architecture Overview

The system uses a service provider pattern combined with Laravel's singleton pattern to provide consistent file operations across different storage backends:

- **Storage Services**: `FileStorageService` (general files) and `AvatarStorageService` (profile images), both extending `AbstractFileStorage`
- **Value Objects**: Immutable objects like `StoredFile`, `FileReference`, `StoredFileIdentifier`, and `StoredFileExtract` represent files at different stages
- **File Interface**: A common `FileInterface` contract shared by `StoredFile`, `StoredFileExtract`, and `FileReference`
- **URL Generation**: All file URLs are routed through `StorageProxyController` via a named route
- **Content Extraction**: Automatic text extraction from uploaded documents for AI consumption
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
│   │   │   │       ├── {uuid}.{extension}   ← the stored file
│   │   │   │       ├── .meta.json           ← metadata (StoredFile serialized)
│   │   │   │       └── output/              ← content extracts
│   │   │   │           └── extract.md
```

Temporary files include a `temp/` prefix: `temp/{category}/{uuid_path}/{uuid}/`

### File Categories

The `StoredFileCategory` enum defines four categories:

| Category         | Value             | Description                              |
|------------------|-------------------|------------------------------------------|
| `ROOM_AVATAR`    | `room_avatars`    | Avatars of groups/rooms                  |
| `PROFILE_AVATAR` | `profile_avatars` | Avatars of users                         |
| `GROUP`          | `group`           | Files shared in group/room chats         |
| `PRIVATE`        | `private`         | Files shared in private AI conversations |

## FileInterface and Implementations

The `FileInterface` (`App\Services\Storage\Interfaces\FileInterface`) defines a common contract for all file representations in the system. It extends `Stringable` and provides methods for accessing file content, metadata, and type information.

### Key Methods

| Method                       | Returns                       | Description                                                 |
|------------------------------|-------------------------------|-------------------------------------------------------------|
| `getOriginalFilename()`      | `string`                      | User-facing filename (may differ from disk filename)        |
| `getDiskFilePath()`          | `string`                      | Path on disk where the file is stored                       |
| `getFileType()`              | `FileType`                    | General file categorization (image, pdf, plain-text, etc.)  |
| `getPlainTextLanguageType()` | `PlainTextLanguageType\|null` | Language type for plain text files (e.g., markdown, python) |
| `getMimeType()`              | `string`                      | MIME type of the file                                       |
| `getContent()`               | `string`                      | File content as a string                                    |
| `getStream()`                | `resource\|null`              | Stream resource for reading file content                    |
| `getSize()`                  | `int`                         | File size in bytes                                          |

### FileType Enum

The `FileType` enum categorizes files into: `IMAGE`, `VIDEO`, `AUDIO`, `WORD_DOCUMENT`, `PDF`, `PLAIN_TEXT`, and `OTHER`. It can be derived from a MIME type using `FileType::fromMimeType()`.

### Implementations

#### FileReference

`FileReference` (`App\Services\Storage\Value\FileReference`) represents a file that may reside in one of three locations:

- **In memory** — raw string content (created via `FileReference::fromContent()`)
- **On the local disk** — an absolute filesystem path (created via `FileReference::fromDisk()`)
- **On a Laravel Filesystem disk** — e.g. S3, local storage disk (created via `FileReference::fromFilesystemDisk()`)

Additional factory methods: `fromUploadedFile()` (from HTTP uploads) and `fromStoredFile()` (from an existing `StoredFile` or `StoredFileExtract`).

`FileReference` is the input type for storing files — it abstracts where the file currently lives so the storage system can handle it uniformly.

#### StoredFile

`StoredFile` (`App\Services\Storage\Value\StoredFile`) is a readonly value object representing a file that has been persisted in the storage system. It is created either by storing a new file (`StoredFile::fromNewFile()`) or by loading metadata from disk (`StoredFile::fromMetaJson()`).

Key additional methods beyond `FileInterface`:

- `getIdentifier()` — returns the `StoredFileIdentifier`
- `getExtracts()` — returns a `FileCollection` of `StoredFileExtract` instances (content extractions)
- `getUrl()` — generates the access URL via the `UrlGenerator`
- `getEtag()` — returns the ETag for caching
- `getMetaDiskFileName()` — path to the `.meta.json` file

`StoredFile` implements `JsonSerializable` and is serialized to `.meta.json` alongside the stored file on disk. The `diskFolderPath` is intentionally **not** persisted — it is injected when loading from JSON, allowing files to be moved on disk without updating metadata.

#### StoredFileExtract

`StoredFileExtract` (`App\Services\Storage\Value\StoredFileExtract`) represents extracted content from a stored file (e.g., text extracted from a PDF via the file converter). Extracts are stored in the `output/` subfolder alongside the source file.

For plain text files, `getContent()` automatically wraps non-markdown content in markdown code fences for proper formatting.

### StoredFileIdentifier

`StoredFileIdentifier` (`App\Services\Storage\Value\StoredFileIdentifier`) is a readonly value object that uniquely identifies a stored file by combining a `StoredFileCategory` and a UUID. It serializes to and from the string format `{category}-{uuid}[.{extension}]`.

Factory methods for various contexts:

- `fromAttachment(Attachment)` — from an Attachment model
- `fromUserAvatar(User)` / `tryFromUserAvatar(User)` — from a User model's avatar
- `fromRoomAvatar(Room)` / `tryFromRoomAvatar(Room)` — from a Room model's icon
- `fromString(string)` — from the serialized string format
- `fromCategoryAndUuid(category, uuid)` — direct construction
- `fromCategoryAndFilename(category, filename)` — generates a new UUID

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

The WebDAV driver is registered in `StorageServiceProvider::boot()`.

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

All storage services implement `StorageServiceInterface`:

#### store()
```php
public function store(FileReference $file, StoredFileCategory $category): StoredFile|null
```

Store a file permanently. Returns a `StoredFile` on success, `null` on failure. Automatically generates a UUID, stores the file on disk, extracts content (if enabled), and writes the `.meta.json` metadata file.

#### storeTemporary()

```php
public function storeTemporary(FileReference $file, StoredFileCategory $category): StoredFile|null
```

Store a file in the temporary location (prefixed with `temp/`). The file should later be persisted via `persistTemporaryFile()`.

#### persistTemporaryFile()

```php
public function persistTemporaryFile(StoredFileIdentifier $identifier): bool
```

Move a file from temporary to permanent storage by relocating its folder.

#### retrieve()
```php
public function retrieve(StoredFileIdentifier|null $identifier, bool $temp = false): ?StoredFile
```

Retrieve a stored file by its identifier. Accepts `null` for convenience with `tryFrom...` methods. Supports legacy files that don't have a `.meta.json` — in that case, metadata is reconstructed from the filesystem and attachment database.

#### delete()
```php
public function delete(StoredFileIdentifier|null $identifier, bool $temp = false): bool
```

Delete a file and its entire directory (including extracts and metadata).

#### getMaxFileSize() / getAllowedMimeTypes()
```php
public function getMaxFileSize(): int
public function getAllowedMimeTypes(): array
```

Return upload constraints. `FileStorageService` includes image types, plain text types, and file converter types. `AvatarStorageService` includes only image types.

### AbstractFileStorage

The `AbstractFileStorage` base class implements all `StorageServiceInterface` methods. Child classes only need to implement `getAllowedMimeTypes()` and optionally disable content extraction by setting `$extractFileContent = false`.

#### Child Classes

- **`FileStorageService`** — General file storage. Accepts images, plain text files, and any file types supported by the configured `FileConverterInterface`. Content extraction is enabled.
- **`AvatarStorageService`** — Avatar image storage. Accepts only image MIME types. Content extraction is disabled (`$extractFileContent = false`).

### File Extension Handling

For security, the storage system normalizes file extensions when writing to disk. Only `pdf`, `doc`, `docx`, `jpg`, `jpeg`, `png`, and `gif` extensions are preserved as-is. All other file types are stored with a `.blob` extension to prevent direct execution of potentially harmful files. The original extension is preserved in the `.meta.json` metadata.

## Temporary Files and Multi-Step Uploads

The storage system uses a two-phase upload process to handle file uploads reliably. Instead of storing files directly into their permanent location, files are first placed in a temporary directory (prefixed with `temp/`) and only moved to permanent storage once the frontend completes the full workflow.

This pattern exists because file uploads are decoupled from the actions that reference them. A typical flow looks like this:

1. **Upload request** — The frontend uploads a file. The backend calls `storeTemporary()`, which stores the file under the `temp/` prefix and returns a `StoredFile` with its identifier.
2. **Attach request** — In a separate HTTP request, the frontend submits the chat message (or other record) and includes the file's identifier. The backend then calls `persistTemporaryFile()` to move the file from the temporary to the permanent location.

If the second request never arrives — for example, the user closes the browser tab, navigates away, or the frontend encounters an error — the file remains in the temporary directory. The `deleteTempExpiredFiles()` cleanup method (run periodically) removes any temporary files older than 5 minutes, preventing orphaned uploads from accumulating on disk.

This approach avoids two problematic alternatives:
- **Storing permanently on upload**: Would leave orphaned files if the user never completes the action, requiring complex garbage collection against the database.
- **Storing only on the final request**: Would require the frontend to hold the entire file in memory or re-upload it with the second request, increasing complexity and payload size.

## StorageProxyController

The `StorageProxyController` (`App\Http\Controllers\StorageProxyController`) handles all file access through a single route (`web.storage.proxy`). It receives a `StoredFileIdentifier` string, parses it, and routes the request based on the file category:

| Category                        | Access Rule                                                                          |
|---------------------------------|--------------------------------------------------------------------------------------|
| `ROOM_AVATAR`, `PROFILE_AVATAR` | Streamed directly (no additional access checks)                                      |
| `GROUP`                         | Verifies the current user is a member of the room the attachment belongs to          |
| `PRIVATE`                       | Verifies the current user owns the private AI conversation the attachment belongs to |

Responses include:

- **ETag-based caching**: Returns `304 Not Modified` if the client sends a matching `If-None-Match` header
- **Streaming**: Files are streamed via `streamDownload()` with proper `Content-Type`, `Cache-Control`, and `ETag` headers
- The original filename is used for the `Content-Disposition` header

## URL Generation

The `UrlGenerator` is a simple readonly class that generates URLs by calling `URL::route()` with the `web.storage.proxy` route name and the file's `StoredFileIdentifier` as a parameter. All file URLs go through the `StorageProxyController`, which handles access control and streaming.

## Service Registration

Services are registered as singletons in `StorageServiceProvider`:

```php
// UrlGenerator — shared singleton using the 'web.storage.proxy' route
$this->app->singleton(UrlGenerator::class, ...);

// AvatarStorageService — uses the 'avatar_storage' disk (default: 'public')
$this->app->singleton(AvatarStorageService::class, ...);

// FileStorageService — uses the 'file_storage' disk (default: 'local_file_storage')
$this->app->singleton(FileStorageService::class, ...);
```

Both services receive a `StorageServiceContext` containing: the filesystem disk, allowed MIME types, max file size, logger, URL generator, content extractor, attachment database, and a clock instance.

## Service Usage

### Storing Files
```php
class MyController extends Controller
{
    public function upload(Request $request, FileStorageService $storage)
    {
        $file = FileReference::fromUploadedFile($request->file('document'));

        // Store temporarily first
        $stored = $storage->storeTemporary($file, StoredFileCategory::GROUP);
        // This is optional, you can also directly persist in one step with store() if you don't need the temp location
        $stored = $storage->store($file, StoredFileCategory::GROUP);

        if ($stored) {
            // Later, persist the file
            $storage->persistTemporaryFile($stored->getIdentifier());

            return response()->json([
                'identifier' => (string) $stored->getIdentifier(),
                'url' => $stored->getUrl()
            ]);
        }

        return response()->json(['error' => 'Upload failed'], 500);
    }
}
```

### Retrieving Files
```php
$file = $storage->retrieve($identifier);
if ($file) {
    $url = $file->getUrl();
    $content = $file->getContent();
    $extracts = $file->getExtracts(); // Content extractions (e.g., text from PDF)
}
```

## Configuration

### Environment Variables

```bash
# Storage Configuration
FILESYSTEM_DISK=local
STORAGE_DISK=local_file_storage
AVATAR_STORAGE=public

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

## File Cleanup and Management

### Automatic Cleanup

The system includes automatic cleanup for temporary files older than 5 minutes:

```php
$cleaned = $storage->deleteTempExpiredFiles();
```

This method iterates over all `temp/` directories, deletes expired files, and removes empty directories afterward.

### Legacy File Support

Files uploaded before the metadata system was introduced are handled transparently. When `retrieve()` encounters a file directory without a `.meta.json`, it:

1. Looks up the original filename from the attachment database
2. Finds any existing content extracts in the `output/` subfolder
3. Reconstructs a `StoredFile` and writes the `.meta.json` for future access

## Security Features

- **File Extension Normalization**: Non-standard extensions are replaced with `.blob` to prevent execution
- **Access Control**: `StorageProxyController` enforces room membership and conversation ownership checks
- **ETag Caching**: Prevents unnecessary file transfers and provides cache validation
- **Category-based Routing**: Different access rules per file category
