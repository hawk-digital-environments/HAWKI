<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Chat\Attachment\AttachmentAccessGuard;
use App\Services\Routing\CacheBusting\CacheBusterGenerator;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Exception\InvalidStorageFileIdentifierStringGivenException;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Interfaces\StorageServiceInterface;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageProxyController extends Controller
{
    public function __construct(
        private readonly CacheBusterGenerator $cacheBusterGenerator,
        private readonly AvatarStorageService $avatarStorage,
        private readonly AttachmentAccessGuard $attachmentAccessGuard,
        private readonly FileStorageService   $fileStorageService,
        #[CurrentUser]
        private readonly User                 $currentUser
    )
    {
    }

    public function streamRouted(Request $request, string $identifierString): StreamedResponse
    {
        try {
            $identifier = StoredFileIdentifier::fromString($identifierString);
        } catch (InvalidStorageFileIdentifierStringGivenException) {
            abort(400, 'Invalid file identifier');
        }

        return match ($identifier->category) {
            StoredFileCategory::ROOM_AVATAR, StoredFileCategory::PROFILE_AVATAR => $this->streamAvatar($request, $identifier),
            StoredFileCategory::GROUP, StoredFileCategory::PRIVATE => $this->streamChatAttachment($request, $identifier),
        };
    }

    private function streamAvatar(Request $request, StoredFileIdentifier $identifier): StreamedResponse
    {
        return $this->createStreamResponse(
            $request,
            $this->getFileOrFail($this->avatarStorage, $identifier)
        );
    }

    private function streamChatAttachment(Request $request, StoredFileIdentifier $identifier): StreamedResponse
    {
        if (!$this->attachmentAccessGuard->canAccess($this->currentUser, $identifier)) {
            abort(403, 'You do not have access to this attachment');
        }

        return $this->createStreamResponse(
            $request,
            $this->getFileOrFail($this->fileStorageService, $identifier),
            cacheControl: 'private, max-age=3600'
        );
    }

    private function getFileOrFail(StorageServiceInterface $storage, StoredFileIdentifier $identifier): StoredFile
    {
        $file = $storage->retrieve($identifier);
        if (!$file) {
            abort(404, 'File not found');
        }
        return $file;
    }

    private function createStreamResponse(
        Request    $request,
        StoredFile $file,
        string     $cacheControl = 'public, max-age=3600'
    ): StreamedResponse
    {
        $etag = $this->cacheBusterGenerator->getEtag($file->getEtag());

        if ($request->headers->get('if-None-match') === $etag) {
            abort(304);
        }

        $stream = $file->getStream();
        if (!$stream) {
            abort(404, 'File not found');
        }

        return response()->streamDownload(
            callback: function () use ($stream) {
                fpassthru($stream);
            },
            name: $file->getOriginalFilename(),
            headers: [
                'Content-Type' => $file->getMimeType(),
                'Cache-Control' => $cacheControl,
                'ETag' => $etag
            ]
        );
    }
}
