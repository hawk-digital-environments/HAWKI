<?php

declare(strict_types=1);

namespace App\Services\Storage;


use App\Models\User;
use App\Services\Storage\Values\StoredFileIdentifier;
use Symfony\Component\Mime\MimeTypes;

/**
 * @api
 *
 * Storage service for user profile avatars and room icons.
 *
 * Accepts common image formats (PNG, JPEG, GIF, BMP, TIFF). Content extraction is
 * explicitly disabled — avatar images do not need to be read as text by AI models.
 *
 * Usage:
 * ```php
 * // Retrieve a user's current avatar (returns null when no avatar is set)
 * $file = $avatarStorageService->retrieveAvatar($user);
 *
 * // Store a new avatar (creates a new StoredFileIdentifier)
 * $stored = $avatarStorageService->store($fileRef, StoredFileCategory::PROFILE_AVATAR);
 * ```
 */
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
