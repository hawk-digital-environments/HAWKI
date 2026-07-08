<?php

namespace App\Services\Storage;


use App\Models\User;
use App\Services\Storage\Values\StoredFileIdentifier;
use Symfony\Component\Mime\MimeTypes;

class AvatarStorageService extends AbstractFileStorage
{
    /**
     * @inheritDoc
     */
    protected bool $extractFileContent = false;

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        $mime = new MimeTypes();

        return $this->filterMimeTypesByAllowed(
            array_merge(
                $mime->getMimeTypes('png'),
                $mime->getMimeTypes('jpeg'),
                $mime->getMimeTypes('jpg'),
                $mime->getMimeTypes('gif'),
                $mime->getMimeTypes('bmp'),
                $mime->getMimeTypes('tiff'),
            )
        );
    }

    /**
     * The same as {@see retrieve} but specifically for retrieving a user's avatar.
     */
    public function retrieveAvatar(User $user): ?Values\StoredFile
    {
        return $this->retrieve(
            StoredFileIdentifier::tryFromUserAvatar($user)
        );
    }
}
