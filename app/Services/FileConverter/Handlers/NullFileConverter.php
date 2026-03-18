<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Handlers;


use Illuminate\Http\UploadedFile;
use Symfony\Component\Finder\SplFileInfo;

/**
 * A file converter that does nothing and returns an empty array.
 * This can be used as a default or fallback converter when no actual conversion is needed.
 * Can be used as a check to see if a converter is active or not.
 */
class NullFileConverter implements FileConverterInterface
{
    public function convert(SplFileInfo|string|UploadedFile $file): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return false;
    }
}
