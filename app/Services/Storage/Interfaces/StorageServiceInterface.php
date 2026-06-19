<?php

namespace App\Services\Storage\Interfaces;

use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;

interface StorageServiceInterface
{
    /**
     * Get the maximum allowed file size for uploads in bytes
     *
     * @return int The maximum file size in bytes
     */
    public function getMaxFileSize(): int;

    /**
     * Get the list of allowed MIME types for file uploads
     *
     * @return array The list of allowed MIME types
     */
    public function getAllowedMimeTypes(): array;

    /**
     * Store a file in storage
     *
     * @param FileReference $file The file to store
     * @param StoredFileCategory $category Optional category to store the file in
     * This is useful if you have to upload a file first and then attach it to a resource in a second API call, to avoid storing files that are never attached to a resource.
     * @return StoredFile|null The instance of the stored file, or null if the file could not be stored (e.g. due to validation errors or storage issues)
     */
    public function store(
        FileReference      $file,
        StoredFileCategory $category
    ): StoredFile|null;

    /**
     * Store a file in a temporary location. This is useful if you have to upload a file first and then attach it to a resource in a second API call,
     * to avoid storing files that are never attached to a resource. The file should be moved to a persistent location later using {@see persistTemporaryFile}.
     *
     * Otherwise, this method behaves the same as {@see store}.
     *
     * @param FileReference $file
     * @param StoredFileCategory $category
     * @return StoredFile|null
     */
    public function storeTemporary(
        FileReference      $file,
        StoredFileCategory $category
    ): StoredFile|null;

    /**
     * Moves a file from the temporary location to a persistent location. This should be called after {@see storeTemporary} to persist the file.
     *
     * @return bool The file contents or null if not found
     */
    public function persistTemporaryFile(StoredFileIdentifier $identifier): bool;

    /**
     * Retrieve a file from storage
     *
     * The identifier can be NULL to allow easy usage with StoredFileIdentifier::tryFrom... methods
     *
     * @return StoredFile|null The file or null if not found
     */
    public function retrieve(StoredFileIdentifier|null $identifier, bool $temp = false): ?StoredFile;

    /**
     * Delete a file from storage
     *
     * The identifier can be NULL to allow easy usage with StoredFileIdentifier::tryFrom... methods
     *
     * @return bool Whether the file was successfully deleted
     */
    public function delete(StoredFileIdentifier|null $identifier, bool $temp = false): bool;

}
