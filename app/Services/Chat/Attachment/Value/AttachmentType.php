<?php

namespace App\Services\Chat\Attachment\Value;

use App\Services\Storage\Values\FileType;

enum AttachmentType: string
{
    case OTHER = 'other';
    case DOCUMENT = 'document';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';

    public static function fromFileType(FileType $mediaType): self
    {
        return match ($mediaType) {
            FileType::IMAGE => self::IMAGE,
            FileType::VIDEO => self::VIDEO,
            FileType::AUDIO => self::AUDIO,
            FileType::WORD_DOCUMENT, FileType::PDF, FileType::PLAIN_TEXT => self::DOCUMENT,
            default => self::OTHER
        };
    }
}
