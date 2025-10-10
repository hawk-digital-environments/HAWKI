<?php

namespace App\Services\Storage;

use App\Services\Storage\Value\StorageFileCategory;
use App\Services\Storage\Value\StorageFileInfo;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

abstract class AbstractFileStorage implements StorageServiceInterface
{
    private array $fileInfoCache = [];
    
    public function __construct(
        protected array $allowedMimeTypes,
        protected int   $maxFileSize,
        protected array $config,
        protected Filesystem $disk,
        protected UrlGenerator $urlGenerator
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }
    
    public function store(
        UploadedFile|string $file,
        string $filename,
        string $uuid,
        string|StorageFileCategory $category,
        bool $temp = false,
        string $subDir = ''
    ): bool
    {
        $categoryObj = $this->ensureCategory($category);
        unset($category); // Ensure we don't use the original variable by mistake
        if ($categoryObj === null) {
            return false;
        }
        
        $uuid = $this->stripUuidExt($uuid);
        
        try {
            // Validate the mime type
            if ($file instanceof UploadedFile) {
                $mimeType = $file->getMimeType();
            } else {
                try {
                    $mimeType = (new MimeTypes())->guessMimeType($filename);
                } catch (\Throwable) {
                    $extension = pathinfo($filename, PATHINFO_EXTENSION);
                    if (is_string($extension)) {
                        $mimeType = (new MimeTypes())->getMimeTypes($extension)[0] ?? null;
                    }
                    
                    if (empty($mimeType)) {
                        Log::warning("Unable to determine mime type for file: $filename");
                        $mimeType = 'application/octet-stream';
                    }
                }
            }
            if (!in_array($mimeType, $this->getAllowedMimeTypes(), true)) {
                Log::warning("Attempted to store file with disallowed mime type: $mimeType");
                return false;
            }
            
            // Validate the file size
            if ($file instanceof UploadedFile) {
                $fileSize = $file->getSize();
            } else {
                try {
                    $fileSize = filesize($file);
                } catch (\Throwable) {
                    // If the file is a string content, we can get its length directly
                    if (is_string($file)) {
                        $fileSize = strlen($file);
                    } else {
                        $fileSize = 0;
                    }
                }
            }
            if ($fileSize === false || $fileSize > $this->maxFileSize) {
                Log::warning("Attempted to store file exceeding max size: $fileSize bytes");
                return false;
            }
            
            if($subDir === ''){
                $path = $this->buildPath($categoryObj, $uuid, $filename, $temp);
            }
            else{
                $path = $this->buildFolder($categoryObj, $uuid, $temp) . $subDir . '/' . $filename;
            }
            
            if ($file instanceof UploadedFile) {
                return $this->disk->putFileAs(dirname($path), $file, basename($path));
            }
            
            return $this->disk->put($path, $file);
        } catch (Throwable $e) {
            Log::error("File storage error: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }
    
    public function moveFileToPersistentFolder(string $uuid, string|StorageFileCategory $category): bool
    {
        $categoryObj = $this->ensureCategory($category);
        unset($category); // Ensure we don't use the original variable by mistake
        if ($categoryObj === null) {
            return false;
        }
        
        $uuid = $this->stripUuidExt($uuid);
        
        try {
            $tempFolder = $this->buildFolder($categoryObj, $uuid, true);
            // Move all files in the main temp folder
            foreach ($this->disk->files($tempFolder) as $file) {
                $fileName = basename($file);
                
                $tempPath = $this->buildPath($categoryObj, $uuid, $fileName, true);
                $newPath = $this->buildPath($categoryObj, $uuid, $fileName, false);

                $this->disk->move($tempPath, $newPath);
            }

            // Move files in subdirectories too, while preserving folder structure
            foreach ($this->disk->allDirectories($tempFolder) as $subDir) {
                foreach ($this->disk->allFiles($subDir) as $subFile) {
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
                'category' => $categoryObj,
            ]);
            return false;
        }
    }
    
    public function retrieve(string $uuid, string|StorageFileCategory $category): ?string
    {
        $info = $this->getFileInfo($uuid, $category);
        if ($info === null) {
            return null;
        }
        
        try {
            return $this->disk->get($info->pathname);
        } catch (Throwable $e) {
            Log::error("File storage retrieve error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * @throws FileNotFoundException
     */
    public function getFileContents(string $uuid, string|StorageFileCategory $category): string
    {
        $info = $this->getFileInfo($uuid, $category);
        
        if ($info === null || !$this->disk->exists($info->pathname)) {
            throw new FileNotFoundException("File not found: $info->pathname");
        }

        // Return the file contents as string
        return $this->disk->get($info->pathname);
    }

    /**
     * @throws FileNotFoundException
     */
    public function streamFile(string $uuid, string|StorageFileCategory $category)
    {
        $info = $this->getFileInfo($uuid, $category);
        if ($info === null) {
            return null;
        }
        
        if (!$this->disk->exists($info->pathname)) {
            throw new FileNotFoundException("File not found: $info->pathname");
        }
        
        return $this->disk->readStream($info->pathname);
    }
    
    /**
     * @inheritDoc
     */
    public function getEtag(string $uuid, string|StorageFileCategory $category): ?string
    {
        $info = $this->getFileInfo($uuid, $category);
        if ($info === null) {
            return null;
        }
        
        if (!$this->disk->exists($info->pathname)) {
            return null;
        }
        
        return md5($this->disk->lastModified($info->pathname) . '-' . $this->disk->size($info->pathname));
    }
    
    /**
     * Retrieve all output files with the specified extension
     */
    public function retrieveOutputFilesByType(string $uuid, string|StorageFileCategory $category, string $fileType): array
    {
        $fileInfo = $this->getFileInfo($uuid, $category);
        if ($fileInfo === null) {
            return [];
        }
        
        try {
            $files = $this->disk->files($fileInfo->outputDirectory);
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
    
    public function delete(string $uuid, string|StorageFileCategory $category): bool
    {
        $fileInfo = $this->getfileInfo($uuid, $category);
        if ($fileInfo === null) {
            return false;
        }
        
        try {
            return $this->disk->deleteDirectory($fileInfo->pathname);
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
            foreach ($this->disk->files($directory) as $file) {
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
    
    /**
     * @inheritDoc
     */
    public function getFileInfo(string $uuid, string|StorageFileCategory $category): ?StorageFileInfo
    {
        if (empty($uuid)) {
            return null;
        }
        
        $categoryObj = $this->ensureCategory($category);
        unset($category); // Ensure we don't use the original variable by mistake
        if ($categoryObj === null) {
            return null;
        }
        
        $uuid = $this->stripUuidExt($uuid);
        
        $cacheKey = $categoryObj->value . '_' . $uuid;
        if (isset($this->fileInfoCache[$cacheKey])) {
            return $this->fileInfoCache[$cacheKey];
        }
        
        try {
            $folder = $this->buildFolder($categoryObj, $uuid);
            $files = $this->disk->files($folder);
            
            if (empty($files)) {
                return null;
            }
            
            $expectedPart = $uuid . DIRECTORY_SEPARATOR . $uuid . '.';
            
            $pathname = null;
            foreach ($files as $file) {
                if (str_contains($file, $expectedPart)) {
                    $pathname = $file;
                    break;
                }
            }
            
            if ($pathname === null) {
                return null;
            }
            
            $directory = dirname($pathname);
            
            $fileInfo = new StorageFileInfo(
                category: $categoryObj,
                directory: $directory,
                outputDirectory: $directory . DIRECTORY_SEPARATOR . 'output',
                pathname: $pathname,
                basename: basename($pathname),
                uuid: $uuid
            );
            
            $this->fileInfoCache[$cacheKey] = $fileInfo;
            
            return $fileInfo;
        } catch (Throwable $e) {
            Log::error("File storage getFileUrl error: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
    
    /**
     * @inheritDoc
     */
    public function getUrl(string $uuid, string|StorageFileCategory $category): ?string
    {
        $info = $this->getFileInfo($uuid, $category);
        
        if ($info === null) {
            return null;
        }
        
        return $this->urlGenerator->generate($info);
    }
    
    protected function buildFolder(StorageFileCategory $category, string $uuid, bool $temp = false): string
    {
        $subStr = str_split(substr($uuid, 0, 4), 1);
        $dir = implode('/', $subStr);
        if($temp){
            return 'temp/' . $category->value . '/' . $dir . '/' . trim($uuid, '/');
        }
        
        return $category->value . '/' . $dir . '/' . trim($uuid, '/');
    }
    
    protected function buildPath(StorageFileCategory $category, string $uuid, string $name, bool $temp = false): string
    {
        $folder = $this->buildFolder($category, $uuid, $temp);
        
        $format = pathinfo($name, PATHINFO_EXTENSION);
        return $folder . '/' . trim($uuid) . '.' . $format;
    }
    
    /**
     * The UUID may be passed as filename including the extension: e.g. "123e4567-e89b-12d3-a456-426614174000.png"
     * This helper strips the extension and returns only the UUID part.
     * @param string $uuid
     * @return string
     */
    protected function stripUuidExt(string $uuid): string
    {
        return explode('.', $uuid, 2)[0];
    }
    
    /**
     * Helper to ensure the category is a valid StorageFileCategory enum value.
     * @param string|StorageFileCategory $category
     * @return StorageFileCategory|null
     */
    protected function ensureCategory(string|StorageFileCategory $category): StorageFileCategory|null
    {
        if (!$category instanceof StorageFileCategory) {
            try {
                return StorageFileCategory::from($category);
            } catch (\ValueError $e) {
                Log::warning("Invalid storage category provided: " . $category, ['exception' => $e]);
                return null;
            }
        }
        
        return $category;
    }
}
