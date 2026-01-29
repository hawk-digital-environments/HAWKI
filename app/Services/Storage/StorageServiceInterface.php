<?php

namespace App\Services\Storage;

use App\Services\Storage\Value\StorageFileCategory;
use App\Services\Storage\Value\StorageFileInfo;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;

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
     * @param UploadedFile|string $file The file to store (UploadedFile or file contents)
     * @param string $filename The name to save the file as
     * @param string $uuid Optional category to store the file in
     * @param string $category Optional category to store the file in
     * @param bool $temp
     * @return bool Whether the file was successfully stored
     */
    public function store(UploadedFile|string $file, string $filename, string $uuid, string $category, bool $temp = false): bool;
    
    /**
     * Move file from temp folder to
     *
     * @param string $uuid The uuid of the file to retrieve
     * @param string $category Optional category the file is stored in
     * @return bool The file contents or null if not found
     */
    public function moveFileToPersistentFolder(string $uuid, string $category): bool;
    
    /**
     * Retrieve a file from storage
     *
     * @param string $uuid The uuid of the file to retrieve
     * @param string|StorageFileCategory $category Optional category the file is stored in
     * @return string|null The file contents or null if not found
     */
    public function retrieve(string $uuid, string|StorageFileCategory $category): ?string;
    
    /**
     * Delete a file from storage
     *
     * @param string $uuid The uuid of the file to delete
     * @param string|StorageFileCategory $category Optional category the file is stored in
     * @return bool Whether the file was successfully deleted
     */
    public function delete(string $uuid, string|StorageFileCategory $category): bool;
    
    /**
     * Get the URL arguments for a stored file
     * The arguments can be used to build the routes for the file proxy service
     * @param string $uuid The uuid of the file
     * @param string|StorageFileCategory $category The category the file is stored in, either as string or enum of that string.
     * @return StorageFileInfo|null
     */
    public function getFileInfo(string $uuid, string|StorageFileCategory $category): ?StorageFileInfo;
    
    /**
     * Get the public URL for a stored file
     *
     * @param string $uuid The uuid of the file
     * @param string|StorageFileCategory $category Optional category the file is stored in
     * @return string|null The public URL or null if file not found
     */
    public function getUrl(string $uuid, string|StorageFileCategory $category): ?string;
    
    /**
     * Returns the file contents as a string
     * @param string $uuid
     * @param string|StorageFileCategory $category
     * @return string
     */
    public function getFileContents(string $uuid, string|StorageFileCategory $category): string;
    
    /**
     * Streams a file
     * @param string $uuid
     * @param string|StorageFileCategory $category
     * @throws FileNotFoundException
     */
    public function streamFile(string $uuid, string|StorageFileCategory $category);
    
    /**
     * Generates an ETag for the file at the given path
     * Returns null if the file does not exist
     * @param string $uuid
     * @param string|StorageFileCategory $category
     * @return string|null
     */
    public function getEtag(string $uuid, string|StorageFileCategory $category): ?string;
}
