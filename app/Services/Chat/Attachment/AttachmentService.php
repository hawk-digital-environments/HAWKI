<?php

namespace App\Services\Chat\Attachment;


use App\Models\AiConvMsg;
use App\Models\Message;
use App\Models\Attachment;

use App\Services\Chat\Attachment\AttachmentFactory;

use App\Services\Storage\FileStorageService;
use App\Services\Storage\Interfaces\StorageServiceInterface;
use App\Services\Storage\StorageServiceFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use Exception;

class AttachmentService{


    public function __construct(
        private FileStorageService $storageService
    ) {}

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
            return $file = $attachmentHandler->retrieveContext($uuid, $category, $outputType);
        }
        else{
            try{
                $file = $this->storageService->retrieveFile($uuid, $category);
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
                $url = $this->storageService->getFileUrl($uuid, $category);
                return $url;
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
            $deleted = $this->storageService->deleteFile($attachment->uuid, $attachment->category);
            if(!$deleted){
                return false;
            }

            $attachment->delete();
            return true;
        }
        catch(Exception $e){
            Log::error(message: "Failed to remove attachment: $e" );
            return false;
        }
    }


    public function convertToAttachmentType($mime){

        if(str_contains($mime, 'pdf') ||
           str_contains($mime, 'word')){
            return 'document';
        }
        if(str_contains($mime, 'image')){
            return 'image';
        }
    }


    public function assignToMessage(AiConvMsg|Message $message, array $data): bool
    {
        try{
            $type = $this->convertToAttachmentType($data['mime']);
            $message->attachments()->create([
                'uuid' => $data['uuid'],
                'name' => $data['name'],
                'category' => $message instanceof AiConvMsg ? 'private' : 'group',
                'mime'=> $data['mime'],
                'type'=> $type,
                'user_id'=> Auth::id()
            ]);
            return true;
        }
        catch(e){
            return false;
        }
    }

}
