<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Symfony\Component\Mime\MimeTypes;

/**
 * @api
 *
 * Storage service for the logos administrators assign to AI providers.
 *
 * Accepts vector (SVG) and common raster image formats. Content extraction is disabled,
 * the images are only ever rendered in the UI. Icons are shared with every signed-in user,
 * the files therefore live on the avatar disk which is already served through the storage proxy.
 *
 * Usage:
 * ```php
 * $stored = $providerIconStorage->store($fileRef, StoredFileCategory::PROVIDER_ICON);
 * $url = $providerIconStorage->urlForUuid($stored->getUuid());
 * ```
 */
class ProviderIconStorageService extends AbstractFileStorage
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
                ['image/svg+xml'],
                $mime->getMimeTypes('png'),
                $mime->getMimeTypes('jpeg'),
                $mime->getMimeTypes('jpg'),
                $mime->getMimeTypes('gif'),
                $mime->getMimeTypes('webp'),
            )
        );
    }

    /**
     * The public URL of a stored icon, derived from its uuid alone (no metadata read).
     */
    public function urlForUuid(?string $uuid): ?string
    {
        if (empty($uuid)) {
            return null;
        }

        return $this->context->urlGenerator->generateForIdentifier(
            StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PROVIDER_ICON, $uuid)
        );
    }
}
