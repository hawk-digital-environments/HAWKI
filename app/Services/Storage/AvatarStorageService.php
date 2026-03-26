<?php

namespace App\Services\Storage;


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
}
