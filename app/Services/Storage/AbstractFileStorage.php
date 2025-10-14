<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;
use App\Services\Storage\UrlGenerator;
use \Illuminate\Contracts\Filesystem\FileNotFoundException;

abstract class AbstractFileStorage implements StorageServiceInterface
{
    public function __construct(
        protected array $config,
        protected Filesystem $disk,
        protected UrlGenerator $urlGenerator
    )
    {
    }

    public function store(
        UploadedFile|string $file,
        string $filename,
        string $uuid,
        string $category,
        bool $temp = false,
        string $subDir = ''
    ): bool
    {
        try {
            if($subDir === ''){
                $path = $this->buildPath($category, $uuid, $filename, $temp);
            }
            else{
                $path = $this->buildFolder($category, $uuid, $temp) . $subDir . '/' . $filename;
            }

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

    public function moveFileToPersistentFolder(string $uuid, string $category): bool
    {
        try {
            $tempFolder = $this->buildFolder($category, $uuid, true);
            // Move all files in the main temp folder
            $files = $this->disk->files($tempFolder);

            foreach ($files as $file) {
                $fileName = basename($file);

                $tempPath = $this->buildPath($category, $uuid, $fileName, true);
                $newPath  = $this->buildPath($category, $uuid, $fileName, false);

                $this->disk->move($tempPath, $newPath);
            }

            // Move files in subdirectories too, while preserving folder structure
            $subDirectories = $this->disk->allDirectories($tempFolder);

            foreach ($subDirectories as $subDir) {
                $subFiles = $this->disk->allFiles($subDir);

                foreach ($subFiles as $subFile) {
                    $fileName = basename($subFile);

                    // Build relative path: preserve the subdirectory name
                    $relativeSubDir = str_replace($tempFolder, '', $subDir);
                    $tempPath = $subFile;
                    $newPath  = str_replace('temp/', '', $subFile); // shift from temp/ to root

                    $this->disk->move($tempPath, $newPath);
                }
            }

            // Clean up old temp folder
            $this->disk->deleteDirectory($tempFolder);

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to move file to storage: " . $e->getMessage(), [
                'exception' => $e,
                'uuid' => $uuid,
                'category' => $category,
            ]);
            return false;
        }
    }


    public function retrieve(string $uuid, string $category): ?string
    {
        try {
            $folder = $this->buildFolder($category, $uuid);
            $files = $this->disk->files($folder);

            if (empty($files)) {
                return null;
            }

            // Get the first file (excluding subdirectories like output/)
            $directFiles = array_filter($files, function($file) use ($folder) {
                $relativePath = str_replace($folder . '/', '', $file);
                return !str_contains($relativePath, '/');
            });

            if (empty($directFiles)) {
                return null;
            }

            $firstFile = array_values($directFiles)[0];
            return $this->disk->get($firstFile);

        } catch (Throwable $e) {
            Log::error("File storage retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * @throws FileNotFoundException
     */
    public function retrieveFromSignedPath(string $path): string
    {
        // Decode if needed (or keep decoded in controller)
        $decodedPath = base64_decode($path);

        if (!$this->disk->exists($decodedPath)) {
            throw new FileNotFoundException("File not found: $decodedPath");
        }

        // Return the file contents as string
        return $this->disk->get($decodedPath);
    }

    /**
     * @throws FileNotFoundException
     */
    public function streamFromSignedPath(string $path)
    {
        $decodedPath = base64_decode($path);
        if (!$this->disk->exists($decodedPath)) {
            throw new FileNotFoundException("File not found: $decodedPath");
        }
        return $this->disk->readStream($decodedPath);
    }

    /**
     * Retrieve all output files with the specified extension
     */
    public function retrieveOutputFilesByType(string $uuid, string $category, string $fileType): array
    {
        try {
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

    public function delete(string $uuid, string $category): bool
    {
        try {
            $folder = $this->buildFolder($category, $uuid);
            return $this->disk->deleteDirectory($folder);
        } catch (Throwable $e) {
            Log::error("File storage delete error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }



    public function deleteTempExpiredFiles(): bool
    {
        $tempFolder = 'temp';
        // 5 Minutes buffer time to prevent accidentally deleting temp files that were in upload process.
        $ttl = 5 * 60;
        $now = time();
        $deleted = false;

        $directories = $this->disk->allDirectories($tempFolder);

        foreach (array_reverse($directories) as $directory) {
            // Get all files recursively in the temp folder
            $files = $this->disk->files($directory);
            foreach ($files as $file) {
                $lastModified = $this->disk->lastModified($file);

                if (($now - $lastModified) > $ttl) {
                    try {
                        $this->disk->delete($file);
                        $deleted = true;
                    } catch (Throwable $e) {
                        Log::warning("Failed to delete temp file: {$file}", ['error' => $e->getMessage()]);
                    }
                }
            }
            //Cleanup empty directories.
            if (empty($this->disk->files($directory)) && empty($this->disk->directories($directory))) {
                $this->disk->deleteDirectory($directory);
            }
        }
        Log::info("Scheduled: File Storage cleanup done successfully: " . ($deleted ? 'true' : 'false, or no expired files found!'));
        return $deleted;
    }

    public function getUrl(string $uuid, string $category): ?string
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
            return $this->urlGenerator->generate($firstFile, $uuid, $category);

        } catch (Throwable $e) {
            Log::error("File storage getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    protected function buildFolder(string $category, string $uuid, bool $temp = false): string
    {
        $subStr = str_split(substr($uuid, 0, 4), 1);
        $dir = join('/', $subStr);
        if($temp){
            return 'temp/' . trim($category, '/',) . '/' . $dir . '/' . trim($uuid, '/');
        }
        else{
            return trim($category, '/',) . '/' . $dir . '/' . trim($uuid, '/');
        }
    }

    protected function buildPath(string $category, string $uuid, string $name, bool $temp = false): string
    {
        $folder = $this->buildFolder($category, $uuid, $temp);

        $exName = explode('.', $name);
        $format = $exName[count($exName) - 1];
        return $folder . '/' . trim($uuid) . '.' . $format;
    }
}
