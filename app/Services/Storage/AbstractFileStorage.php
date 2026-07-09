<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Storage\Interfaces\StorageServiceInterface;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StorageServiceContext;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileExtract;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Contracts\Filesystem\Filesystem;
use JsonException;
use Symfony\Component\Filesystem\Path;

/**
 * Common implementation for all file storage services.
 *
 * Subclasses define which MIME types are accepted ({@see getAllowedMimeTypes()}) and whether
 * content extraction should run after a file is written ({@see $extractFileContent}).
 *
 * ## File layout on disk
 * Each file is stored inside its own folder, sharded by the first four characters of its UUID
 * to avoid filesystem limits on large directories:
 * ```
 * {category}/{uuid[0]}/{uuid[1]}/{uuid[2]}/{uuid[3]}/{uuid}/{uuid}.{ext|blob}
 *                                                           └─ .meta.json      (sidecar)
 *                                                           └─ output/          (extracts)
 * ```
 * Temporary files live under an additional `temp/` prefix and are moved by {@see persistTemporaryFile()}.
 *
 * ## Two-step upload flow
 * When a file must be uploaded before the owning resource exists (e.g. a file attachment
 * before the message is sent), use the two-step flow to avoid orphaned permanent files:
 * ```php
 * // Step 1 — upload lands in temp/
 * $stored = $storageService->storeTemporary($fileRef, StoredFileCategory::PRIVATE);
 *
 * // Step 2 — after the message is persisted, move to permanent storage
 * $storageService->persistTemporaryFile($stored->getIdentifier());
 * ```
 *
 * ## Security: blob extension
 * Unknown file extensions are stored with a `.blob` extension on disk to prevent browsers from
 * executing them if the storage disk is publicly accessible. The original extension is preserved
 * in the `.meta.json` sidecar and restored when the file is served.
 *
 * ## Legacy support
 * Files uploaded before the `.meta.json` sidecar was introduced are handled transparently by
 * {@see retrieve()}: on first access a meta file is synthesised from the attachment database
 * and written to disk so subsequent accesses hit the fast path.
 */
abstract class AbstractFileStorage implements StorageServiceInterface
{
    /**
     * The name of the metadata file stored alongside files.
     */
    public const META_FILE_NAME = '.meta.json';

    /**
     * The name of the directory where content extracts are stored.
     */
    public const EXTRACT_FOLDER_NAME = 'output';

    /**
     * If set to false, the file content will not be extracted and stored in the meta information when a file is stored.
     * Decide if it makes sense for your storage implementation. For example if you are uploading avatars
     * you probably don't need to extract the content, but for document uploads it might be useful to have the content available.
     * @var bool
     */
    protected bool $extractFileContent = true;

    public function __construct(
        protected readonly StorageServiceContext $context
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getMaxFileSize(): int
    {
        return $this->context->maxFileSize;
    }

    /**
     * @inheritDoc
     */
    public function store(FileReference $file, StoredFileCategory $category): StoredFile|null
    {
        return $this->storeInternal($file, $category, false);
    }

    /**
     * @inheritDoc
     */
    public function storeTemporary(FileReference $file, StoredFileCategory $category): StoredFile|null
    {
        return $this->storeInternal($file, $category, true);
    }

    /**
     * Internal method to handle the storage of both temporary and permanent files.
     *
     * @param FileReference $file The file to be stored.
     * @param StoredFileCategory $category The category under which the file should be stored.
     * @param bool $temp Indicates whether the file should be stored as temporary or permanent.
     * @return StoredFile|null The stored file object on success, or null on failure.
     */
    private function storeInternal(
        FileReference      $file,
        StoredFileCategory $category,
        bool               $temp
    ): StoredFile|null
    {
        try {
            $identifier = StoredFileIdentifier::fromCategoryAndFilename($category, $file->getOriginalFilename());
            $storageDiskFilePath = $this->buildPath($identifier, $file->getOriginalFilename(), $temp);

            if (!$this->context->filesystem->writeStream(
                path: $storageDiskFilePath,
                resource: $file->getStream()
            )) {
                $this->context->logger->error("Failed to store file: " . $file->getOriginalFilename());
                return null;
            }

            $storedFile = StoredFile::fromNewFile(
                storageDiskFilePath: $storageDiskFilePath,
                identifier: $identifier,
                filesystemFile: $file,
                extracts: $this->context->contentExtractor->getExtracts(
                    storageDiskFilePath: $storageDiskFilePath,
                    file: FileReference::fromFilesystemDisk(
                        diskFilePath: $storageDiskFilePath,
                        filesystem: $this->context->filesystem,
                        originalFilename: $file->getOriginalFilename()
                    ),
                    filesystem: $this->context->filesystem,
                    extractContent: $this->extractFileContent
                ),
                filesystem: $this->context->filesystem,
                urlGenerator: $this->context->urlGenerator,
                createdAt: $this->context->clock->now()
            );

            if (!$this->context->filesystem->put(
                $storedFile->getMetaDiskFileName(),
                json_encode($storedFile, JSON_THROW_ON_ERROR)
            )) {
                $this->context->logger->error(
                    "Failed to store file metadata for file: " . $file->getOriginalFilename());
                return null;
            }

            return $storedFile;
        } catch (\Throwable $e) {
            $this->context->logger->error("File storage error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function persistTemporaryFile(StoredFileIdentifier $identifier): bool
    {
        try {
            $tempFolder = $this->buildFolder($identifier, true);
            $persistentFolder = $this->buildFolder($identifier, false);
            $this->context->filesystem->move($tempFolder, $persistentFolder);
            return true;
        } catch (\Throwable $e) {
            $this->context->logger->error("Failed to move file to storage: " . $e->getMessage(), [
                'exception' => $e,
                'identifier' => (string)$identifier
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieve(StoredFileIdentifier|null $identifier, bool $temp = false): ?StoredFile
    {
        if ($identifier === null) {
            return null;
        }

        try {
            $diskFolderName = $this->buildFolder($identifier, $temp);
            $metaDiskFilename = Path::join($diskFolderName, self::META_FILE_NAME);
            if ($this->context->filesystem->exists($metaDiskFilename)) {
                return StoredFile::fromMetaJson(
                    $this->context->filesystem->get($metaDiskFilename),
                    $diskFolderName,
                    $this->context->filesystem,
                    $this->context->urlGenerator
                );
            }

            // The folder does not exist AND there is no metadata? Not found
            if (!$this->context->filesystem->exists($diskFolderName)) {
                return null;
            }

            return $this->retrieveLegacyFile(
                identifier: $identifier,
                storageDiskFolderPath: $diskFolderName,
                filesystem: $this->context->filesystem
            );
        } catch (\Throwable $e) {
            $this->context->logger->error("File storage retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Initializes a legacy stored file by retrieving its metadata and content extracts.
     *
     * @param StoredFileIdentifier $identifier
     * @param string $storageDiskFolderPath The folder path containing the file.
     * @param Filesystem $filesystem The filesystem instance.
     * @return StoredFile|null The initialized stored file or null if no files are found.
     * @throws JsonException
     */
    private function retrieveLegacyFile(
        StoredFileIdentifier $identifier,
        string               $storageDiskFolderPath,
        Filesystem           $filesystem
    ): ?StoredFile
    {
        $files = $filesystem->files($storageDiskFolderPath);
        $directFiles = array_filter($files, static function ($file) use ($storageDiskFolderPath) {
            return !str_contains(Path::makeRelative($file, $storageDiskFolderPath), '/');
        });

        if (empty($directFiles)) {
            return null;
        }

        $storageDiskFilePath = reset($directFiles);

        $attachment = $this->context->attachmentRepository->findOneByStoredFileIdentifier($identifier);
        if ($attachment !== null) {
            $originalFilename = $attachment->name;
        } else {
            $extension = pathinfo($storageDiskFilePath, PATHINFO_EXTENSION);
            $originalFilename = 'file-without-a-name_' . md5(basename($storageDiskFilePath)) . '.' . $extension;
        }

        $file = FileReference::fromFilesystemDisk($storageDiskFilePath, $filesystem, $originalFilename);

        $extracts = $this->findLegacyExtracts($file, $filesystem);

        $createdAt = $attachment?->created_at->toDateTimeImmutable()
            ?? new \DateTimeImmutable('@' . $filesystem->lastModified($storageDiskFilePath));

        $file = StoredFile::fromNewFile(
            storageDiskFilePath: $storageDiskFilePath,
            identifier: $identifier,
            filesystemFile: $file,
            extracts: $extracts,
            filesystem: $filesystem,
            urlGenerator: $this->context->urlGenerator,
            createdAt: $createdAt
        );

        $filesystem->put($file->getMetaDiskFileName(), json_encode($file, JSON_THROW_ON_ERROR));

        return $file;
    }

    /**
     * Finds and retrieves content extracts for a legacy file.
     *
     * In this context "LEGACY" means, a file that has been uploaded
     * before the "meta" file information storage has been introduced.
     * The process retroactively generates a meta file based on the information
     * we can retrieve from our filesystem.
     *
     * @param FileReference $sourceFile
     * @param Filesystem $filesystem The filesystem instance.
     * @return FileCollection A collection of stored file extracts.
     */
    private function findLegacyExtracts(
        FileReference $sourceFile,
        Filesystem    $filesystem
    ): FileCollection
    {
        $diskFolderName = dirname($sourceFile->getDiskFilePath());
        $extractsFolderPath = Path::join($diskFolderName, self::EXTRACT_FOLDER_NAME);
        if (!$filesystem->exists($extractsFolderPath)) {
            return new FileCollection();
        }

        $extracts = [];

        foreach ($filesystem->files($extractsFolderPath, true) as $extractFilePath) {
            $extracts[] = StoredFileExtract::fromExtractFile(
                storageDiskFilePath: $sourceFile->getDiskFilePath(),
                extractFile: FileReference::fromFilesystemDisk($extractFilePath, $filesystem),
                filesystem: $filesystem
            );
        }

        return new FileCollection(...$extracts);
    }

    /**
     * @inheritDoc
     */
    public function delete(StoredFileIdentifier|null $identifier, bool $temp = false): bool
    {
        if ($identifier === null) {
            return false;
        }

        try {
            return $this->context->filesystem->deleteDirectory($this->buildFolder($identifier, $temp));
        } catch (\Throwable $e) {
            $this->context->logger->error("File storage delete error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Removes files from the `temp/` area that have not been moved to permanent storage within
     * five minutes of being written, then cleans up any resulting empty directories.
     *
     * The five-minute buffer prevents accidentally deleting a file that is still mid-upload.
     * This method is intended to be called by a scheduled command, not on every request.
     *
     * @return bool True if at least one file was deleted, false when nothing expired.
     */
    public function deleteTempExpiredFiles(): bool
    {
        $tempFolder = 'temp';
        // 5 Minutes buffer time to prevent accidentally deleting temp files that were in upload process.
        $ttl = 5 * 60;
        $now = time();
        $deleted = false;

        $directories = $this->context->filesystem->allDirectories($tempFolder);

        foreach (array_reverse($directories) as $directory) {
            // Get all files recursively in the temp folder
            foreach ($this->context->filesystem->files($directory) as $file) {
                $lastModified = $this->context->filesystem->lastModified($file);

                if (($now - $lastModified) > $ttl) {
                    try {
                        $this->context->filesystem->delete($file);
                        $deleted = true;
                    } catch (\Throwable $e) {
                        $this->context->logger->warning("Failed to delete temp file: {$file}", ['error' => $e->getMessage()]);
                    }
                }
            }
            //Cleanup empty directories.
            if (empty($this->context->filesystem->files($directory)) && empty($this->context->filesystem->directories($directory))) {
                $this->context->filesystem->deleteDirectory($directory);
            }
        }
        $this->context->logger->info("Scheduled: File Storage cleanup done successfully: " . ($deleted ? 'true' : 'false, or no expired files found!'));
        return $deleted;
    }

    /**
     * Builds the folder path for a file based on its category and UUID.
     *
     * @param StoredFileIdentifier $identifier
     * @param bool $temp Whether the folder is temporary.
     * @return string The folder path.
     */
    protected function buildFolder(StoredFileIdentifier $identifier, bool $temp = false): string
    {
        $pathParts = [];
        if ($temp) {
            $pathParts[] = 'temp';
        }
        $pathParts[] = $identifier->category->value;
        array_push($pathParts, ... str_split(substr($identifier->uuid, 0, 4)));
        $pathParts[] = $identifier->uuid;

        return Path::join(...$pathParts);
    }

    /**
     * Builds the full file path for a file based on its category, UUID, and name.
     *
     * @param StoredFileIdentifier $identifier
     * @param string $filename The name of the file.
     * @param bool $temp Whether the file is temporary.
     * @return string The full file path.
     */
    protected function buildPath(StoredFileIdentifier $identifier, string $filename, bool $temp = false): string
    {
        $folder = $this->buildFolder($identifier, $temp);

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // For legacy reasons we keep pdf, doc and docx, as well as image extensions as-is,
        // However every thing else gets converted into a ".blob" extension, to prevent
        // people opening / executing potentially harmful files directly from the storage.
        // The original file extension is still stored in the meta information, so it can be used for
        // correct handling when retrieving the file.
        $extension = in_array(strtolower($extension), ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'], true) ? $extension : 'blob';

        return Path::join($folder, $identifier->uuid . '.' . $extension);
    }

    /**
     * Intersects the MIME types supported by the current service (images, converter output, etc.)
     * with the administrator-configured allow-list from {@see StorageServiceContext::$allowedMimeTypes}.
     *
     * When the allow-list is empty (not configured), all available types are returned.
     * When the allow-list is non-empty but nothing matches, a warning is logged and all
     * available types are returned as a safe fallback to avoid silently rejecting all uploads.
     *
     * @param string[] $availableMimeTypes MIME types supported by this service implementation.
     * @return string[] De-duplicated, lower-cased list of MIME types to advertise and accept.
     */
    protected function filterMimeTypesByAllowed(array $availableMimeTypes): array
    {
        $availableMimeTypes = array_values(array_unique(array_map('strtolower', $availableMimeTypes)));

        if (empty($this->context->allowedMimeTypes)) {
            return $availableMimeTypes;
        }

        $filteredMimeTypes = array_filter($availableMimeTypes, function ($mimeType) {
            return in_array($mimeType, $this->context->allowedMimeTypes, true);
        });

        if (empty($filteredMimeTypes)) {
            $this->context->logger->warning('The configured allowed MIME types do not match any of the available MIME types. Defaulting to all available MIME types.');
            return $availableMimeTypes;
        }

        return $filteredMimeTypes;
    }
}
