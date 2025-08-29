<?php
namespace App\Services\Chat\Attachment\Handlers;

use Illuminate\Support\Str;

use App\Services\Storage\FileStorageService;
use App\Services\Chat\Attachment\Interfaces\AttachmentInterface;
use App\Toolkit\FileConverter\DocumentConverter;

use Exception;
use Illuminate\Support\Facades\Log;

class AtchDocumentHandler implements AttachmentInterface
{
    public function __construct(
        protected FileStorageService $storageService
    ){
    }

    public function store($file, string $category): array
    {
        // Generate a unique filename to prevent overwriting
        $uuid = Str::uuid();
        $originalName = $file->getClientOriginalName();

        $stored = $this->storageService->storeFile($file, $originalName, $uuid, $category);
        if (!$stored) {
            return [
                'success' => false,
                'message'=> 'Failed to store file.'
            ];
            // throw new \Exception('Failed to store file.');
        }
        $url = $this->storageService->getFileUrl($uuid, $category);
        $results = $this->extractFileContent($file);
        if (!$results) {
            return [
                'success' => false,
                'uuid' => $uuid,
                'message'=> 'Failed to extract text from file'
            ];
            // throw new \Exception('Failed to store file.');
        }

        foreach($results as $relativePath => $content){
            $filename = 'output/' . basename($relativePath);
            $stored = $this->storageService->storeFile($content, $filename, $uuid, $category);
        }

        return [
            'success' => true,
            'uuid' => $uuid,
            'url'=> $url
        ];
    }

    public function extractFileContent($file): ?array{
        try{
            $fileConverter = new DocumentConverter();
            $results = $fileConverter->requestDocumentToMarkdown($file);
            return $results;
        }
        catch(Exception $e){
            return null;
        }
    }

    public function retrieveContext(string $uuid, string $category, $fileType = 'md'): ?string{
        $files = $this->storageService->retrieveOutputFilesByType($uuid, $category, $fileType);
        if($files || count($files) > 0){
            $results = [];
            foreach($files as $file){
                $content = $file['contents'];
                $html_safe = htmlspecialchars($content);
                $results[] = $html_safe;
            }
            return $results[0];
        }

        try{
            $file = $this->storageService->retrieveFile($uuid, $category);
            $results = $this->extractFileContent($file);
            if($results !== null){
                foreach($results as $relativePath => $content){
                    $filename = 'output/' . basename($relativePath);
                    $this->storageService->storeFile($content, $filename, $uuid, $category);
                }
                return $this->retrieveContext($uuid, $category);
            }
            else{
                return "Unable to extract content at the moment. please try again later. If the problem persists please contact the adminstrator.";
            }

        }
        catch(Exception $e){
            return "Unable to extract content at the moment. please try again later. If the problem persists please contact the adminstrator.";
        }

    }

}
