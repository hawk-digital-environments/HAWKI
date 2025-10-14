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
     * @param string $category Optional category the file is stored in
     * @return string|null The file contents or null if not found
     */
    public function retrieve(string $uuid, string $category): ?string;

    /**
     * Delete a file from storage
     *
     * @param string $uuid The uuid of the file to delete
     * @param string $category Optional category the file is stored in
     * @return bool Whether the file was successfully deleted
     */
    public function delete(string $uuid, string $category): bool;

    /**
     * Get the public URL for a stored file
     *
     * @param string $uuid The uuid of the file
     * @param string $category Optional category the file is stored in
     * @return string|null The public URL or null if file not found
     */
    public function getUrl(string $uuid, string $category): ?string;

}
