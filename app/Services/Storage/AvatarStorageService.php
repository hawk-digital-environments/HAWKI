<?php

namespace App\Services\Storage;

use App\Services\Storage\StorageServiceInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class AvatarStorageService
{

    public function __construct(
        protected Filesystem $disk
    )
    {
    }

    public function storeFile(UploadedFile|string $file, string $category, string $name, string $uuid): ?string
    {
        try {
            $extension = 'bin'; // fallback if unknown
            $contents = null;

            if ($file instanceof UploadedFile) {
                $extension = $file->getClientOriginalExtension();
                $contents = file_get_contents($file->getRealPath());
            } elseif (is_string($file)) {
                // detect data URI format
                if (preg_match('/^data:image\/(\w+);base64,/', $file, $matches)) {
                    $extension = $matches[1]; // e.g. "jpeg", "png"
                    $file = substr($file, strpos($file, ',') + 1);
                    $contents = base64_decode($file);
                } else {
                    // assume raw string content
                    $contents = $file;
                }
            }

            $path = $this->buildPath($category, $name, $uuid . '.' . $extension);

            if ($this->disk->put($path, $contents)) {
                return $path;
            }

            return null;
        } catch (Throwable $e) {
            Log::error("Avatar storage error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function retrieveFile(string $category, string $name, string $uuid = null)
    {
        try {
            $folder = $this->buildFolder($category, $name);
            $files = $this->disk->files($folder);

            if (empty($files)) {
                return false;
            }

            if (!$uuid) {
                $file = $files[0];
            } else {
                // Find file with matching UUID in its filename
                $file = collect($files)->first(function ($f) use ($uuid) {
                    return str_contains(basename($f), $uuid);
                });

                if (!$file) {
                    return false; // No matching file found
                }
            }
            return $this->disk->get($file);
        } catch (Throwable $e) {
            Log::error("Avatar storage retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    public function deleteFile(string $category, string $name, string $uuid = null): bool
    {
        try {

            $file = $this->buildPath($category, $name, $uuid);
            return $this->disk->delete($file);
        } catch (Throwable $e) {
            Log::error("Avatar storage delete error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    public function getFileUrl(string $category, string $name, string $uuid = null): ?string
    {
        try {
            $folder = $this->buildFolder($category, $name);
            $files = $this->disk->files($folder);
            if (empty($files)) {
                return null;
            }

            if (!$uuid) {
                $file = $files[0];
            } else {
                // Find file with matching UUID in its filename
                $file = collect($files)->first(function ($f) use ($uuid) {
                    return str_contains(basename($f), $uuid);
                });

                if (!$file) {
                    return false; // No matching file found
                }
            }
            return $this->generateUrl($file);
        } catch (Throwable $e) {
            Log::error("Avatar storage getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    protected function buildFolder(string $category, string $name): string
    {
        return trim($category, '/') . '/' . trim($name, '/');
    }

    protected function buildPath(string $category, string $name, string $uuid): string
    {
        return trim($category, '/') . '/' . trim($name, '/') . '/' . trim($uuid, '/');
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
                return $this->disk->url($path);
            }
        }

        // For public disks (local public), return regular URL
        return $this->disk->url($path);
    }
}
