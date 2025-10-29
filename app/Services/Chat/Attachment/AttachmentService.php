<?php

namespace App\Services\Chat\Attachment;


use App\Events\AttachmentAssignedToMessageEvent;
use App\Events\AttachmentRemovedFromMessageEvent;
use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Models\Message;
use App\Services\Storage\FileStorageService;
use Exception;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttachmentService{


    public function __construct(
        private readonly FileStorageService $storageService,
        #[Config('filesystems.upload_limits.max_attachment_files')]
        private readonly int                $maxAttachments = 0
    ) {}
    
    /**
     * Get allowed mime types for file uploads.
     *
     * @return array
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->storageService->getAllowedMimeTypes();
    }
    
    /**
     * Get maximum file size for uploads in bytes.
     *
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->storageService->getMaxFileSize();
    }
    
    /**
     * Get maximum number of attachments allowed.
     * If 0, then unlimited attachments are allowed.
     * @return int
     */
    public function getMaxAttachments(): int
    {
        return $this->maxAttachments;
    }

    public function store($file, $category): ?array
    {
        try{
            // GET FILE TYPE
            $mime = $file->getMimeType();
            $type = $this->convertToAttachmentType($mime);
            // CREATE HANDLER
            $attachmentHandler = AttachmentFactory::create($type);
            // STORE FILE BASED ON TYPE
            $result = $attachmentHandler->store($file, $category);

            return $result;
        }
        catch(Exception $e){
            Log::error("Error storing file: $e");
            return null;
        }
    }



    public function retrieve(Attachment $attachment, $outputType = null)
    {
        $uuid = $attachment->uuid;
        $category = $attachment->category;

        if($outputType){
            $attachmentHandler = AttachmentFactory::create($attachment->type);
            return $attachmentHandler->retrieveContext($uuid, $category, $outputType);
        }
        else{
            try{
                $file = $this->storageService->retrieve($uuid, $category);
                return $file;
            }
            catch(Exception $e){
                Log::error("Error retrieving file", ["UUID"=> $uuid, "category"=> $category]);
                return null;
            }
        }
    }


    public function getFileUrl(Attachment $attachment, $outputType = null)
    {
        $uuid = $attachment->uuid;
        $category = $attachment->category;

        if($outputType){
            $urls = $this->storageService->getOutputFilesUrls($uuid, $category, $outputType);
            return $urls[0];
        }
        else{
            try{
                return $this->storageService->getUrl($uuid, $category);
            }
            catch(Exception $e){
                Log::error("Error retrieving file", ["UUID"=> $uuid, "category"=> $category]);
                return null;
            }
        }
    }



    public function delete(Attachment $attachment): bool
    {
        try{
            $deleted = $this->storageService->delete($attachment->uuid, $attachment->category);
            if(!$deleted){
                return false;
            }
            
            $attachable = $attachment->attachable;
            if ($attachable instanceof Message) {
                AttachmentRemovedFromMessageEvent::dispatch($attachment, $attachable);
            }

            $attachment->delete();
            return true;
        }
        catch(Exception $e){
            Log::error(message: "Failed to remove attachment: $e" );
            return false;
        }
    }
    
    public function convertToAttachmentType($mime): string
    {
        if(str_contains($mime, 'pdf') ||
           str_contains($mime, 'word')){
            return 'document';
        }
        if(str_contains($mime, 'image')){
            return 'image';
        }
        throw new \RuntimeException('Unsupported mime type: ' . $mime);
    }
    
    
    public function assignToMessage(AiConvMsg|Message $message, array $data): bool
    {
        try{
            $category = $message instanceof AiConvMsg ? 'private' : 'group';
            $this->storageService->moveFileToPersistentFolder($data['uuid'], $category);

            $type = $this->convertToAttachmentType($data['mime']);
            $attachment = $message->attachments()->create([
                'uuid' => $data['uuid'],
                'name' => $data['name'],
                'category' => $category,
                'mime'=> $data['mime'],
                'type'=> $type,
                'user_id'=> Auth::id()
            ]);
            
            if ($message instanceof Message) {
                AttachmentAssignedToMessageEvent::dispatch($message, $attachment);
            }
            
            return true;
        }
        catch(Exception $e){
            return false;
        }
    }

}
