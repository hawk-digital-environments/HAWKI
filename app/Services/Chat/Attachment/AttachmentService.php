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
use Illuminate\Http\UploadedFile;

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


    /**
     * Store a file and immediately create an Attachment record for API use.
     * Unlike the web flow (which defers DB record creation until message send),
     * this method creates the Attachment record right away so the UUID can be
     * referenced in a subsequent AI request.
     *
     * @param UploadedFile $file
     * @return array|null  Returns ['uuid', 'name', 'mime', 'type'] on success, null on failure.
     */
    public function storeForApi(UploadedFile $file): ?array
    {
        try {
            $mime = $file->getMimeType();
            $type = $this->convertToAttachmentType($mime);

            if (!$type) {
                throw new Exception("Unsupported file type: $mime");
            }

            $handler = AttachmentFactory::create($type);
            $result  = $handler->store($file, 'private');

            $attachment = Attachment::create([
                'uuid'     => $result['uuid'],
                'name'     => $file->getClientOriginalName(),
                'category' => 'private',
                'mime'     => $mime,
                'type'     => $type,
                'user_id'  => Auth::id(),
            ]);

            return [
                'uuid' => $attachment->uuid,
                'name' => $attachment->name,
                'mime' => $attachment->mime,
                'type' => $attachment->type,
            ];
        } catch (Exception $e) {
            Log::error('Error storing API attachment', ['exception' => $e]);
            return null;
        }
    }


    public function assignToMessage(AiConvMsg|Message $message, array $data): ?string
    {
        try{
            $category = $message instanceof AiConvMsg ? 'private' : 'group';
            $this->storageService->moveFileToPersistentFolder($data['uuid'], $category);

            $type = $this->convertToAttachmentType($data['mime']);
            $message->attachments()->create([
                'uuid' => $data['uuid'],
                'name' => $data['name'],
                'category' => $category,
                'mime'=> $data['mime'],
                'type'=> $type,
                'user_id'=> Auth::id()
            ]);
            return true;
        }
        catch(Exception $e){
            return false;
        }
    }

}
