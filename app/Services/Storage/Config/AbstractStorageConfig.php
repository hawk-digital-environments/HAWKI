<?php
declare(strict_types=1);


namespace App\Services\Storage\Config;


use App\Services\Config\AbstractConfig;
use Symfony\Component\Mime\MimeTypes;

abstract class AbstractStorageConfig extends AbstractConfig
{
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
