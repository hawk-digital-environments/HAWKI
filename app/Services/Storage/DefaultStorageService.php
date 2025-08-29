<?php

namespace App\Services\Storage;

use App\Services\Storage\StorageServiceInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class DefaultStorageService implements StorageServiceInterface
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
            Log::error("Default storage error: " . $e->getMessage(), ['exception' => $e]);
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

            $firstFile = $files[0];
            return $this->disk->get($firstFile);
        } catch (Throwable $e) {
            Log::error("Default storage retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    public function deleteFile(string $uuid, string $category): bool
    {
        try {
            $folder = $this->buildFolder($category, $uuid);
            return $this->disk->deleteDirectory($folder);
        } catch (Throwable $e) {
            Log::error("Default storage delete error: " . $e->getMessage(), ['exception' => $e]);
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

            $firstFile = $files[0];
            return $this->generateUrl($firstFile);
        } catch (Throwable $e) {
            Log::error("Default storage getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
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
