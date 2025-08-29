<?php

namespace App\Services\Storage;

use App\Services\Storage\StorageServiceInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class FileStorageService implements StorageServiceInterface
{
    public function __construct(
        protected Filesystem $disk
    )
    {
    }

    public function storeFile(UploadedFile|string $file, string $filename, string $uuid, string $category): bool
    {
        try {
            $path = $this->buildPath($category, $uuid, $filename);

            if ($file instanceof UploadedFile) {
                return $this->disk->put($path, file_get_contents($file->getRealPath()));
            } else {
                return $this->disk->put($path, $file);
            }
        } catch (Throwable $e) {
            Log::error("File storage error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    public function retrieveFile(string $uuid, string $category)
    {
        try {
            $folder = $this->buildFolder($category, $uuid);
            $files = $this->disk->files($folder);

            if (empty($files)) {
                return false;
            }

            // Get the first file (excluding subdirectories like output/)
            $directFiles = array_filter($files, function($file) use ($folder) {
                $relativePath = str_replace($folder . '/', '', $file);
                return !str_contains($relativePath, '/');
            });

            if (empty($directFiles)) {
                return false;
            }

            $firstFile = array_values($directFiles)[0];
            return $this->disk->get($firstFile);
        } catch (Throwable $e) {
            Log::error("File storage retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    public function deleteFile(string $uuid, string $category): bool
    {
        try {
            $folder = $this->buildFolder($category, $uuid);
            return $this->disk->deleteDirectory($folder);
        } catch (Throwable $e) {
            Log::error("File storage delete error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    public function getFileUrl(string $uuid, string $category): ?string
    {
        try {
            $folder = $this->buildFolder($category, $uuid);
            $files = $this->disk->files($folder);

            if (empty($files)) {
                return null;
            }

            // Get the first direct file (not in subdirectories)
            $directFiles = array_filter($files, function($file) use ($folder) {
                $relativePath = str_replace($folder . '/', '', $file);
                return !str_contains($relativePath, '/');
            });

            if (empty($directFiles)) {
                return null;
            }

            $firstFile = array_values($directFiles)[0];
            return $this->generateUrl($firstFile);
        } catch (Throwable $e) {
            Log::error("File storage getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Delete the "output" folder and all its contents for a specific file (UUID/category).
     */
    public function deleteConvertedFiles(string $uuid, ?string $category = null): bool
    {
        try {
            $category = $category ?? 'default';
            $outputFolder = $this->buildFolder($category, $uuid) . '/output';
            return $this->disk->deleteDirectory($outputFolder);
        } catch (Throwable $e) {
            Log::error("Failed to delete output folder: " . $e->getMessage(), ['outputFolder' => $outputFolder ?? 'unknown']);
            return false;
        }
    }

    /**
     * Get URLs for all output files of a given type
     */
    public function getOutputFilesUrls(string $uuid, ?string $category = null, string $fileType): array
    {
        try {
            $category = $category ?? 'default';
            $outputFolder = $this->buildFolder($category, $uuid) . '/output';
            $files = $this->disk->files($outputFolder);
            $urls = [];

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                    $urls[] = $this->generateUrl($file);
                }
            }

            return $urls;
        } catch (Throwable $e) {
            Log::error("File storage getOutputFilesUrls error: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Retrieve all output files with the specified extension
     */
    public function retrieveOutputFilesByType(string $uuid, string $category, string $fileType): array
    {
        try {
            $category = $category ?? 'default';
            $outputFolder = $this->buildFolder($category, $uuid) . '/output';
            $files = $this->disk->files($outputFolder);
            $matches = [];

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($fileType)) {
                    $matches[] = [
                        'path' => $file,
                        'contents' => $this->disk->get($file),
                    ];
                }
            }

            return $matches;
        } catch (Throwable $e) {
            Log::error("File storage retrieveOutputFilesByType error: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    protected function buildFolder(string $category, string $uuid): string
    {
        return trim($category, '/') . '/' . trim($uuid, '/');
    }

    protected function buildPath(string $category, string $uuid, string $name): string
    {
        return trim($category, '/') . '/' . trim($uuid, '/') . '/' . trim($name, '/');
    }

    /**
     * Generate appropriate URL based on disk type
     */
    protected function generateUrl(string $path): string
    {
        // Check if disk supports temporary URLs (S3, NextCloud, SFTP, etc.)
        if (method_exists($this->disk, 'temporaryUrl')) {
            try {
                // Generate a temporary URL that expires in 24 hours for private disks
                return $this->disk->temporaryUrl($path, now()->addHours(24));
            } catch (Throwable $e) {
                Log::warning("Failed to generate temporary URL, falling back to regular URL: " . $e->getMessage());
                return $this->disk->url($path);
            }
        }

        // For public disks (local public), return regular URL
        return $this->disk->url($path);
    }
}
