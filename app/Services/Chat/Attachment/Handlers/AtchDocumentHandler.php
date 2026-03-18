<?php
namespace App\Services\Chat\Attachment\Handlers;

use App\Services\Chat\Attachment\Interfaces\AttachmentInterface;
use App\Services\FileConverter\Handlers\FileConverterInterface;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

class AtchDocumentHandler implements AttachmentInterface
{
    public function __construct(
        protected FileStorageService     $storageService,
        protected FileConverterInterface $fileConverter,
        protected LoggerInterface        $logger
    ){
    }

    public function store($file, string $category): array
    {
        // Generate a unique filename to prevent overwriting
        $uuid = Str::uuid();
        $originalName = $file->getClientOriginalName();

        $stored = $this->storageService->store($file, $originalName, $uuid, $category, true);
        if (!$stored) {
            return [
                'success' => false,
                'message'=> 'Failed to store file.'
            ];
        }

        if (!$this->extractAndPersistFileContents($file, $uuid, $category, true)) {
            return [
                'success' => false,
                'uuid' => $uuid,
                'message'=> 'Failed to extract text from file'
            ];
        }

        return [
            'success' => true,
            'uuid' => $uuid,
        ];
    }

    /**
     * Retrieves the extracted content of a file based on its UUID and category.
     * This method first checks if we already have extracted content available in storage, and if so, it returns that content.
     * If not, it checks if the file converter is available before attempting to retrieve the original file and extract content from it.
     * If the converter is unavailable or if there are issues with retrieval or extraction, it returns appropriate error messages.
     */
    public function retrieveContext(string $uuid, string $category, string $fileType = 'md'): string
    {
        $content = $this->retrieveExtractedContent($uuid, $category, $fileType);

        // If we found existing content, we can simply return it.
        if ($content !== null) {
            if (empty($content)) {
                // However, if the content is empty, we should tell the model/user that the file is empty or that there was an issue with the extraction, rather than returning an empty string which could be confusing.
                $this->logger->debug('Extracted content is empty for file with UUID: ' . $uuid);
                return "It seems that the file was processed but no content could be extracted. This could be due to the file being empty, or the content not being in a format that can be extracted. If you believe this is an error, please contact the administrator.";
            }
            return $content;
        }

        // If we currently do not have any extracted content, we should check if the converter is available before attempting to retrieve the file and extract content.
        // This can be a costly operation and will likely fail if the converter is unavailable.
        if (!$this->fileConverter->isAvailable()) {
            $this->logger->debug('The file converter is not available. Skipping content retrieval for file with UUID: ' . $uuid);
            return "The file converter is currently unavailable. Unable to extract content at the moment. This is a consistent issue until the converter becomes available again, so please try again later. If the problem persists please contact the adminstrator.";
        }

        // Fetch the source file from storage. If the file cannot be retrieved, we should return an error message rather than attempting extraction
        // which will fail and may cause unnecessary load on the system.
        $file = $this->storageService->retrieve($uuid, $category);
        if ($file === null) {
            $this->logger->warning('No file found with UUID: ' . $uuid);
            return "Unable to extract content at the moment. please try again later. If the problem persists please contact the adminstrator.";
        }

        // Extract content and persist output files.
        if (!$this->extractAndPersistFileContents($file, $uuid, $category)) {
            $this->logger->warning('Content extraction failed for file with UUID: ' . $uuid);
            return "The file was processed but no content could be extracted. This could be due to the file being empty, or the content not being in a format that can be extracted. If you believe this is an error, please contact the administrator.";
        }

        // After attempting extraction, we should now be able to retrieve the extracted content.
        return $this->retrieveExtractedContent($uuid, $category, $fileType)
            ?? 'We extracted the contents of the file, but there is no content for the requested file type. Please use another file type if available, or contact the administrator if you believe this is an error.';
    }

    /**
     * Helper method to retrieve extracted content of a specific type from storage.
     * This method assumes that the content has already been extracted and persisted by the `extractAndPersistFileContents` method.
     * If the content is not found, it returns null. This allows the calling method to decide how to handle the absence of content
     * (e.g., by returning an error message or attempting extraction).
     */
    private function retrieveExtractedContent(string $uuid, string $category, string $fileType): ?string
    {
        $files = $this->storageService->retrieveOutputFilesByType($uuid, $category, $fileType);
        if (!empty($files)) {
            $file = array_shift($files);
            return htmlspecialchars($file['contents'], ENT_QUOTES);
        }
        return null;
    }

    /**
     * Helper method to extract content from a file and persist the extracted content back to storage.
     * This method checks if the file converter is available before attempting extraction, and it handles any exceptions that may occur during conversion.
     * It returns true if content was successfully extracted and persisted, or false if there was an issue
     * (e.g., converter unavailable, conversion error, no content extracted).
     */
    private function extractAndPersistFileContents(UploadedFile|SplFileInfo|string $file, string $uuid, string $category, bool $temp = false): bool
    {
        if (!$this->fileConverter->isAvailable()) {
            $this->logger->debug('The file converter is not available. Skipping content extraction for file with UUID: ' . $uuid);
            return false;
        }

        try {
            $filesToPersist = $this->fileConverter->convert($file);
        } catch (\Throwable $e) {
            $this->logger->error("Error converting file: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }

        if (empty($filesToPersist)) {
            $this->logger->warning('No content extracted from file with UUID: ' . $uuid);
            return false;
        }

        foreach ($filesToPersist as $relativePath => $content) {
            $this->storageService->storeOutputFile($content, $relativePath, $uuid, $category, $temp);
        }

        return count($filesToPersist) > 0;
    }
}
