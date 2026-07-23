<?php

declare(strict_types=1);

namespace App\Services\Storage\Values;

use Symfony\Component\Mime\MimeTypes;

/**
 * Broad file-type categorisation used to decide how a file should be rendered or handled
 * (e.g. showing an image preview vs. a document download link).
 *
 * Unlike MIME type, this enum collapses hundreds of MIME variants into a small set of
 * UI-relevant buckets. Plain-text files carry additional context via {@see PlainTextLanguageType}.
 */
enum FileType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case WORD_DOCUMENT = 'word-document';
    case PDF = 'pdf';
    case PLAIN_TEXT = 'plain-text';
    case OTHER = 'other';

    /**
     * Determines the FileType based on the provided MIME type.
     *
     * @param string $mimeType The MIME type to evaluate.
     * @return self The corresponding FileType.
     */
    public static function fromMimeType(string $mimeType): self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::IMAGE;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::VIDEO;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return self::AUDIO;
        }

        $mime = new MimeTypes();
        $wordMimeTypes = array_unique([
            ...$mime->getMimeTypes('docx'),
            ...$mime->getMimeTypes('doc'),
        ]);
        if (in_array($mimeType, $wordMimeTypes, true)) {
            return self::WORD_DOCUMENT;
        }

        if (in_array($mimeType, $mime->getMimeTypes('pdf'), true)) {
            return self::PDF;
        }

        $plainTextOrScriptMimeTypes = PlainTextLanguageType::getMimeTypes();
        if (in_array($mimeType, $plainTextOrScriptMimeTypes, true)) {
            return self::PLAIN_TEXT;
        }

        return self::OTHER;
    }
}
