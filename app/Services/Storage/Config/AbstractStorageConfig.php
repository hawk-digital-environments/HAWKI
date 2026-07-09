<?php
declare(strict_types=1);


namespace App\Services\Storage\Config;


use App\Services\Config\AbstractConfig;
use Symfony\Component\Mime\MimeTypes;

/**
 * Base class for storage-related public config objects. Provides a shared helper for
 * deriving allowed file extensions from a list of MIME types.
 */
abstract class AbstractStorageConfig extends AbstractConfig
{
    /**
     * Converts a list of MIME types into the file extensions recognised by Symfony's MIME type database.
     * Extensions that contain non-alphanumeric characters (e.g. `[1-9]`) are filtered out because
     * browsers and file-input elements would not accept them as valid extension filters.
     *
     * @param string[] $mimeTypes
     * @return string[]
     */
    protected static function extensionsFromMimeTypes(array $mimeTypes): array
    {
        $mime = new MimeTypes();
        $extensions = [];
        foreach ($mimeTypes as $mimeType) {
            $extensions[] = $mime->getExtensions($mimeType);
        }
        return array_values(
            array_filter(
                array_unique(array_merge(...$extensions)),
                static function ($ext) {
                    // Some extensions are weird like "[1-9]", we want to filter those out, basically contain only characters and numbers
                    return preg_match('/^[a-zA-Z0-9+]+$/', $ext);
                }
            )
        );
    }
}
