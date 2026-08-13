# Storage & Files

HAWKI's file storage layer is built around one security property: **no direct storage URLs are ever exposed to clients**. Every file a user can download travels through a PHP controller that checks access rights and streams the content. There is no public S3 bucket URL, no publicly-accessible `storage/` path for chat attachments.

This page covers the storage architecture. Text extraction from documents is in [File Converter](./320-File-Converter.md).

## The storage proxy

`App\Http\Controllers\StorageProxyController` is the single entry point for all file access (see [Life of a Request](../../100-Tutorials/100-Life-of-a-Request.md) for a full walk). It parses a `StoredFileIdentifier` string, dispatches by category, retrieves the file bytes via the appropriate storage service, and returns a `StreamedResponse` with ETag-based caching headers.

Access rules by category:

| Category | Who can access |
|---|---|
| `PROFILE_AVATAR` | Anyone (no membership check) |
| `ROOM_AVATAR` | Anyone (no membership check) |
| `GROUP` | Only users who are members of the room the attachment belongs to |
| `PRIVATE` | Only the owner of the private AI conversation the attachment belongs to |

Avatar files are public-ish metadata — seeing another user's avatar or a room's icon does not reveal private content. Attachment files carry potentially sensitive content and are always access-controlled.

## `StoredFileIdentifier` and `StoredFileCategory`

`App\Services\Storage\Values\StoredFileIdentifier` is the central handle for a file in storage. It combines a category and a UUID into a short string: `{category}-{uuid}[.extension]`. Examples: `private-550e8400-...pdf`, `group-a1b2c3d4-...`, `room_avatars-b3c4d5e6-...`. The extension is metadata only — it restores the correct filename when serving. The actual file on disk may have a `.blob` extension for security.

`StoredFileCategory` is a backed enum: `ROOM_AVATAR`, `PROFILE_AVATAR`, `GROUP`, `PRIVATE`. The category value becomes the top-level directory on the storage disk. Open the enum for the canonical case list.

Factory methods on `StoredFileIdentifier`: `fromString($routeParam)`, `tryFromUserAvatar($user)`, `tryFromRoomAvatar($room)`, `fromCategoryAndFilename($category, $filename)`, `fromCategoryAndUuid($category, $uuid)`.

## File layout on disk

4-level UUID sharding avoids filesystem limits on directories with large numbers of files:

```
{category}/{uuid[0]}/{uuid[1]}/{uuid[2]}/{uuid[3]}/{uuid}/
├── {uuid}.{ext|blob}   ← the stored file
├── .meta.json          ← metadata sidecar
└── output/             ← content extracts (if any)
    └── extract.md
```

Temporary files (pre-message-send uploads) live under an additional `temp/` prefix.

### `.blob` extension security

Only `pdf`, `doc`, `docx`, `jpg`, `jpeg`, `png`, `gif` extensions are preserved on disk. All other file types are stored with a `.blob` extension — prevents a browser from executing a file if the storage disk is ever misconfigured to be publicly accessible. The original extension is saved in `.meta.json` and restored when the file is served.

### `.meta.json` sidecar

Every stored file has a `.meta.json` sidecar generated at write time: original filename, MIME type, file size, content references, creation timestamp. When `retrieve()` encounters a file directory without a `.meta.json` (a pre-metadata legacy file), it reconstructs the sidecar from the attachment database and writes it to disk so subsequent accesses take the fast path.

## Two-step upload flow

Attachments must be uploaded before the message that references them exists. To avoid orphaned permanent files, the storage system uses two phases:

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

If the user abandons the message without sending, the `filestorage:cleanup` artisan command removes temporary files older than 5 minutes during its next scheduled run (see [Artisan Commands](../../500-Reference/100-Artisan-Commands.md)).

## Upload constraints

Two configuration values from the `hawki-core` config block (delivered via the connection bootstrap — see [Config Blocks](../../200-Concepts/200-Config-Blocks.md)) feed the frontend's pre-upload validation:

- `storage_files.allowed_mimes` — MIME types the frontend checks before staging an upload
- `storage_files.max_file_size` — maximum file size in bytes (default 20 MB / 20971520)

The frontend validates against these values before uploading. The backend enforces the same constraints during `store()`. Both layers must agree — if you change the backend allowlist, also update the config the frontend reads. `MAX_ATTACHMENT_FILES` (default `0` = unlimited) controls the maximum number of attachments per message.

## Storage services

- **`FileStorageService`** (`App\Services\Storage\FileStorageService`) — general file uploads (group room attachments, private conversation files). Content extraction is enabled: every file stored triggers a text extraction pass so AI models can read document contents. Accepted MIME types are the union of common image types, all plain-text and source-code types known to `PlainTextLanguageType`, and any type the active `FileConverterInterface` accepts. An admin-configured MIME allowlist can further restrict runtime acceptance.
- **`AvatarStorageService`** — avatar uploads. Content extraction is disabled; accepted MIME types are image types only. Maximum avatar file size is 2 MB (separate from the general attachment limit).
- **`AbstractFileStorage`** — both services extend this base, which implements the `StorageServiceInterface` contract (`store`, `storeTemporary`, `persistTemporaryFile`, `retrieve`, `delete`, `getMaxFileSize`, `getAllowedMimeTypes`).
- **`ContentExtractor`** — decouples text extraction from storage. Runs after a file is written and calls the active `FileConverterInterface` to produce extract files in the `output/` subfolder. The `StoredFile`'s `getExtracts()` returns a `FileCollection` of `StoredFileExtract` instances the AI service reads as context.

## Storage backends

The storage disk is configured per service in `config/filesystems.php` and selected by environment variables: Local (`local_file_storage`) for development and single-server deployments, Amazon S3 (`s3`) for cloud, Nextcloud WebDAV (`nextcloud`) for on-premise, SFTP (`sftp`) for any SFTP-accessible server. The `check:storage {--filesystem=}` artisan command smoke-tests a backend (write / read / delete) without requiring a full upload.

## Garbage collection

`filestorage:cleanup` deletes temporary files older than 5 minutes and attachment files whose parent message or conversation has been deleted (6-month retention for soft-deleted content). Run on a schedule in production; the default Docker setup includes it as a scheduled task.
