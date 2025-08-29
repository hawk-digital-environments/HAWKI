<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;

interface StorageServiceInterface
{
    /**
     * Store a file in storage
     *
     * @param UploadedFile|string $file The file to store (UploadedFile or file contents)
     * @param string $filename The name to save the file as
     * @param string|null $category Optional category to store the file in
     * @return bool Whether the file was successfully stored
     */
    public function storeFile(UploadedFile|string $file, string $filename, string $uuid, string $category): bool;

    /**
     * Retrieve a file from storage
     *
     * @param string $filename The name of the file to retrieve
     * @param string|null $category Optional category the file is stored in
     * @return string|null The file contents or null if not found
     */
    public function retrieveFile(string $uuid, string $category);

    /**
     * Delete a file from storage
     *
     * @param string $filename The name of the file to delete
     * @param string|null $category Optional category the file is stored in
     * @return bool Whether the file was successfully deleted
     */
    public function deleteFile(string $uuid, string $category): bool;

    /**
     * Get the public URL for a stored file
     *
     * @param string $filename The name of the file
     * @param string|null $category Optional category the file is stored in
     * @return string|null The public URL or null if file not found
     */
    public function getFileUrl(string $uuid, string $category): ?string;

}
