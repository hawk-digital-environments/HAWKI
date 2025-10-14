<?php

namespace App\Services\Chat\Attachment;

use App\Services\Chat\Attachment\Interfaces\AttachmentInterface;
use App\Services\Chat\Attachment\Handlers\AtchDocumentHandler;
use App\Services\Chat\Attachment\Handlers\AtchImageHandler;

use App\Services\Chat\Attachment\InvalidArgumentException;


class AttachmentFactory
{
    /**
     * Create a storage service based on configuration
     *
     * @param string|null $type Optional storage type, defaults to configured default
     * @return AttachmentInterface
     */
    public static function create(string $type): ?AttachmentInterface
    {
        return match ($type) {
            'image'    => app(AtchImageHandler::class),
            'document' => app(AtchDocumentHandler::class),
            default    => throw new \InvalidArgumentException("Unknown attachment type: $type"),
        };
    }
}
