<?php

namespace App\Http\Controllers;

use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\StorageServiceInterface;
use App\Services\Storage\Value\StorageFileCategory;
use App\Services\Storage\Value\StorageFileInfo;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageProxyController extends Controller
{
    public function __construct(
        private readonly AvatarStorageService $avatarStorage,
        private readonly FileStorageService   $fileStorageService,
        #[CurrentUser]
        private readonly User                 $currentUser
    )
    {
    }
    
    public function streamRouted(Request $request, string $category, string $filename): StreamedResponse
    {
        try {
            $categoryObj = StorageFileCategory::from($category);
        } catch (\ValueError) {
            abort(400, 'Invalid category to stream file');
        }
        
        return match ($categoryObj) {
            StorageFileCategory::ROOM_AVATAR, StorageFileCategory::PROFILE_AVATAR => $this->streamAvatar($request, $filename, $categoryObj),
            StorageFileCategory::GROUP => $this->streamGroupFile($request, $filename, $categoryObj),
            StorageFileCategory::PRIVATE => $this->streamPrivateFile($request, $filename, $categoryObj),
        };
    }
    
    private function streamAvatar(Request $request, string $filenameOrUuid, StorageFileCategory $category): StreamedResponse
    {
        return $this->createStreamResponse(
            $request,
            $this->avatarStorage,
            $this->getFileInfoOrFail($this->avatarStorage, $filenameOrUuid, $category)
        );
    }
    
    private function streamGroupFile(Request $request, string $filenameOrUuid, StorageFileCategory $category): StreamedResponse
    {
        $fileInfo = $this->getFileInfoOrFail($this->fileStorageService, $filenameOrUuid, $category);
        
        $attachment = Attachment::where('uuid', $fileInfo->uuid)->firstOrFail();
        $attachable = $attachment->attachable;
        if (!$attachable instanceof Message) {
            abort(400, 'Invalid request, attachment is not linked to a message');
        }
        
        if (!$attachable->room->isMember($this->currentUser->id)) {
            abort(403, 'You are not a member of the room this attachment belongs to');
        }
        
        return $this->createStreamResponse(
            $request,
            $this->fileStorageService,
            $fileInfo,
            $attachment->name
        );
    }
    
    private function streamPrivateFile(Request $request, string $filenameOrUuid, StorageFileCategory $category): StreamedResponse
    {
        $fileInfo = $this->getFileInfoOrFail($this->fileStorageService, $filenameOrUuid, $category);
        
        $attachment = Attachment::where('uuid', $fileInfo->uuid)->firstOrFail();
        $attachable = $attachment->attachable;
        if (!$attachable instanceof AiConvMsg) {
            abort(400, 'Invalid request, attachment is not linked to a private ai conversation');
        }
        
        if ($attachable->user_id !== $this->currentUser->id) {
            abort(403, 'You do not have access to the private conversation this attachment belongs to');
        }
        
        return $this->createStreamResponse(
            $request,
            $this->fileStorageService,
            $fileInfo,
            $attachment->name
        );
    }
    
    private function getFileInfoOrFail(StorageServiceInterface $storage, string $uuid, StorageFileCategory $category): StorageFileInfo
    {
        $fileInfo = $storage->getFileInfo($uuid, $category);
        if (!$fileInfo) {
            abort(404, 'File not found');
        }
        return $fileInfo;
    }
    
    private function createStreamResponse(
        Request                 $request,
        StorageServiceInterface $storage,
        StorageFileInfo         $fileInfo,
        ?string                 $filename = null,
    ): StreamedResponse
    {
        if ($request->headers->get('if-None-match') === $storage->getEtag($fileInfo->uuid, $fileInfo->category)) {
            abort(304);
        }
        
        return response()->streamDownload(function () use ($storage, $fileInfo) {
            fpassthru($storage->streamFile($fileInfo->uuid, $fileInfo->category));
        }, $filename ?? $fileInfo->basename, [
            'Content-Type' => $fileInfo->mimeType,
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $storage->getEtag($fileInfo->uuid, $fileInfo->category)
        ]);
    }
}
