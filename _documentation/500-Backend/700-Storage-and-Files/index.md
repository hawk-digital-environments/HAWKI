---
sidebar_position: 1
---

# Storage & Files

HAWKI's file storage layer is built around one security property: **no direct storage URLs are ever exposed to clients**. Every file a user can download travels through a PHP controller that checks access rights and streams the content. There is no public S3 bucket URL, no publicly-accessible `storage/` path for chat attachments.

This article covers the storage architecture, the proxy controller, the `StoredFileIdentifier` format, the two-step upload flow, and configuration. The file conversion pipeline (for extracting text from documents) is covered in [100-File-Converter](100-File-Converter.md).

## The Storage Proxy

`App\Http\Controllers\StorageProxyController` is the single entry point for all file access. It is registered at the route `web.storage.proxy` and accepts a `StoredFileIdentifier` string as a path parameter.

```
GET /proxy/storage/{identifier}
```

The controller:

1. Parses the identifier string into a `StoredFileIdentifier`.
2. Dispatches to a private method based on `$identifier->category`.
3. Retrieves the file bytes via the appropriate storage service.
4. Returns a `StreamedResponse` with ETag-based caching headers.

### Access rules by category

| Category | Who can access |
|---|---|
| `PROFILE_AVATAR` | Anyone (no membership check) |
| `ROOM_AVATAR` | Anyone (no membership check) |
| `GROUP` | Only users who are members of the room the attachment belongs to |
| `PRIVATE` | Only the owner of the private AI conversation the attachment belongs to |

Avatar files are considered public-ish metadata — seeing another user's avatar or a room's icon does not reveal private content. Attachment files carry potentially sensitive content and are always access-controlled.

### ETag caching

The controller checks the `If-None-Match` request header against an ETag derived from the file's stored metadata. If they match, it returns `304 Not Modified` without re-streaming the file body. This allows browsers and HTTP caches to avoid redundant downloads.

## `StoredFileIdentifier`

`App\Services\Storage\Values\StoredFileIdentifier` is the central handle for a file in storage. It combines a category and a UUID into a short string that can be passed through routes, stored in database columns, and serialized to JSON.

**String format:** `{category}-{uuid}[.{extension}]`

Examples:

```
private-550e8400-e29b-41d4-a716-446655440000.pdf
group-a1b2c3d4-e5f6-7890-abcd-ef1234567890
room_avatars-b3c4d5e6-f7a8-9012-bcde-f01234567890
```

The extension is optional and is metadata only — it is the original file extension and is used to restore the correct filename when serving the file. The actual file on disk may have a `.blob` extension for security reasons (see below).

Factory methods:

```php
// From a route parameter or stored string
$id = StoredFileIdentifier::fromString($routeParam);

// From a User or Room model (for avatars)
$id = StoredFileIdentifier::tryFromUserAvatar($user); // returns null if no avatar
$id = StoredFileIdentifier::tryFromRoomAvatar($room);

// Create a new identifier for an upload
$id = StoredFileIdentifier::fromCategoryAndFilename(StoredFileCategory::GROUP, 'document.pdf');

// From a known UUID
$id = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
```

### Frontend UUID linkage

The UUID component of a `StoredFileIdentifier` is the same value that appears as the `uuid` field in `OldUiFileData` on the frontend. When the backend returns a stored file identifier after upload, the frontend records the UUID and includes it in the message payload when sending a message. This links the backend storage identity to the frontend's attachment tracking.

## `StoredFileCategory`

`App\Services\Storage\Values\StoredFileCategory` is a backed enum:

| Case | Value | Used for |
|---|---|---|
| `ROOM_AVATAR` | `'room_avatars'` | Group room icons |
| `PROFILE_AVATAR` | `'profile_avatars'` | User profile avatars |
| `GROUP` | `'group'` | Files attached to group room messages |
| `PRIVATE` | `'private'` | Files attached to private AI conversation messages |

The category value becomes the top-level directory on the storage disk.

## File Layout on Disk

Files are organized with 4-level UUID sharding to avoid filesystem limits on directories with large numbers of files:

```
{category}/
└── {uuid[0]}/
    └── {uuid[1]}/
        └── {uuid[2]}/
            └── {uuid[3]}/
                └── {uuid}/
                    ├── {uuid}.{ext|blob}   ← the stored file
                    ├── .meta.json          ← metadata sidecar
                    └── output/             ← content extracts (if any)
                        └── extract.md
```

Temporary files (pre-message-send uploads) live under an additional `temp/` prefix:

```
temp/{category}/{uuid[0]}/{uuid[1]}/{uuid[2]}/{uuid[3]}/{uuid}/
```

### `.blob` extension security

Only the extensions `pdf`, `doc`, `docx`, `jpg`, `jpeg`, `png`, and `gif` are preserved as-is on disk. All other file types are stored with a `.blob` extension. This prevents a browser from executing a file if the storage disk is ever misconfigured to be publicly accessible. The original extension is saved in `.meta.json` and restored when the file is served.

### `.meta.json` sidecar

Every stored file has a `.meta.json` sidecar generated at write time. It contains:

- Original filename
- MIME type
- File size
- Content references (paths to extract files in `output/`)
- Creation timestamp

When `retrieve()` encounters a file directory without a `.meta.json` (a pre-metadata legacy file), it reconstructs the sidecar from the attachment database and writes it to disk so subsequent accesses take the fast path.

## Two-Step Upload Flow

Attachments must be uploaded before the message that references them exists. To avoid orphaned permanent files, the storage system uses a two-phase approach:

```
Step 1 — Upload
Client → POST /upload
Backend: FileStorageService::storeTemporary($fileRef, StoredFileCategory::GROUP)
↳ File lands in temp/{category}/...
↳ Returns StoredFile with identifier

Step 2 — Message send (separate request)
Client → POST /room/{slug}/message  [includes attachment UUIDs]
Backend: GroupMessageHandler::create(...)
↳ FileStorageService::persistTemporaryFile($identifier) for each UUID
↳ Moves file from temp/ to permanent location
↳ AttachmentRepository::assignToMessage($message, $storedFile, $user)
```

If the user abandons the message without sending, the `filestorage:cleanup` artisan command removes temporary files older than 5 minutes during its next scheduled run.

## Upload Constraints and Frontend Validation

Two configuration values from the `hawki-core` config block (delivered via the connection bootstrap) feed the frontend's pre-upload validation in `AttachmentAspect.add()`:

| Config key | Default | Description |
|---|---|---|
| `storage_files.allowed_mimes` | (from `config/filesystems.php`) | MIME types the frontend checks before staging an upload |
| `storage_files.max_file_size` | 20971520 (20 MB) | Maximum file size in bytes |

The frontend validates against these values before uploading. The backend enforces the same constraints during `store()`. Both layers must agree — if you change the backend allowlist, also update the config that the frontend reads, or the frontend will allow files that the backend rejects.

:::caution
The default `max_file_size` is **20 MB** (20971520 bytes). Older documentation may incorrectly state 10 MB. The correct value is in `config/filesystems.php` (key `MAX_ATTACHMENT_SIZE`, env `MAX_ATTACHMENT_SIZE`, default `20971520`).
:::

Additional constraint: `MAX_ATTACHMENT_FILES` controls the maximum number of attachments per message (default `0` = unlimited).

## Storage Services

### `FileStorageService`

`App\Services\Storage\FileStorageService` handles general file uploads — group room attachments and private conversation files. Content extraction is enabled: every file stored via this service triggers a text extraction pass so AI models can read document contents.

Accepted MIME types are the union of:
- Common image types: PNG, JPEG/JPG, GIF
- All plain-text and source-code types known to `PlainTextLanguageType`
- Any type the active `FileConverterInterface` implementation accepts (e.g. PDF, Word documents)

An admin-configured MIME allowlist (`storage_files.allowed_mimes`) can further restrict which types are accepted at runtime.

### `AvatarStorageService`

`App\Services\Storage\AvatarStorageService` handles avatar uploads. Content extraction is disabled (`$extractFileContent = false`). Accepted MIME types are image types only.

Maximum avatar file size is 2 MB (separate from the general attachment limit).

### `AbstractFileStorage`

Both services extend `App\Services\Storage\AbstractFileStorage`, which implements the `StorageServiceInterface` contract:

```php
store(FileReference $file, StoredFileCategory $category): StoredFile|null
storeTemporary(FileReference $file, StoredFileCategory $category): StoredFile|null
persistTemporaryFile(StoredFileIdentifier $identifier): bool
retrieve(StoredFileIdentifier|null $identifier, bool $temp = false): ?StoredFile
delete(StoredFileIdentifier|null $identifier, bool $temp = false): bool
getMaxFileSize(): int
getAllowedMimeTypes(): array
```

### `ContentExtractor`

`App\Services\Storage\Utils\ContentExtractor` decouples text extraction from storage. It runs after a file is written and calls the active `FileConverterInterface` to produce extract files in the `output/` subfolder. The `StoredFile`'s `getExtracts()` method returns a `FileCollection` of `StoredFileExtract` instances, each of which the AI service reads as context for the conversation.

## Storage Backends

The storage disk is configured per service in `config/filesystems.php` and selected by environment variables:

| Backend | When to use |
|---|---|
| Local (`local_file_storage`) | Development and single-server deployments |
| Amazon S3 (`s3`) | Cloud deployments; configure `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `S3_REGION`, `S3_DEFAULT_BUCKET` |
| Nextcloud WebDAV (`nextcloud`) | On-premise deployments using Nextcloud |
| SFTP (`sftp`) | Any SFTP-accessible server |

The `check:storage {--filesystem=}` artisan command smoke-tests a backend (write / read / delete) without requiring a full upload.

## Garbage Collection

`filestorage:cleanup` deletes:

1. Temporary files that have been in `temp/` for more than 5 minutes (unconfirmed uploads)
2. Attachment files whose parent message or conversation has been deleted (6-month retention period for soft-deleted content)

Run this command on a schedule in production. The default Docker setup includes it as a scheduled task.
