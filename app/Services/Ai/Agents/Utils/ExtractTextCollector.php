<?php

declare(strict_types=1);

namespace App\Services\Ai\Agents\Utils;

use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\StoredFile;

/**
 * Collects inlineable text from a stored file's PLAIN_TEXT extracts, mirroring
 * the PLAIN_TEXT dispatch in {@see UserMessageAttachments::register()}.
 *
 * Non-text extracts (images, binary — e.g. a WebP image pulled out of a PDF)
 * are skipped: their raw bytes carry no prompt text and would break the AI
 * provider's JSON encoding (Guzzle's json_encode throws on malformed UTF-8).
 * Extracted text is UTF-8-sanitized so the encoder never receives bad bytes.
 *
 * Currently used by {@see \App\Services\Assistant\AssistantPromptComposer} for
 * assistant knowledge files. 
 * TODO unify with UserMessageAttachments.
 */
class ExtractTextCollector
{
    /**
     * Concatenated, sanitized text of the file's PLAIN_TEXT extracts.
     * Returns an empty string when the file has no text extracts.
     */
    public function collect(StoredFile $file): string
    {
        $extracts = $file->getExtracts();

        if (null === $extracts) {
            return '';
        }

        $parts = [];

        foreach ($extracts as $extract) {
            if ($extract->getFileType() !== FileType::PLAIN_TEXT) {
                continue;
            }

            $text = self::sanitizeUtf8($extract->getContent());

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Guarantee valid UTF-8 before the text reaches the provider's JSON
     * encoder. Invalid byte sequences are replaced (U+FFFD) rather than
     * crashing the whole request.
     */
    public static function sanitizeUtf8(string $text): string
    {
        return mb_check_encoding($text, 'UTF-8')
            ? $text
            : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
