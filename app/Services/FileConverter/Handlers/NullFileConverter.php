<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Handlers;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Http\UploadedFile;

/**
 * A file converter that does nothing and returns an empty array.
 * This can be used as a default or fallback converter when no actual conversion is needed.
 * Can be used as a check to see if a converter is active or not.
 */
class NullFileConverter implements FileConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function isValidConfig(array $config): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function setConfig(array $config): void
    {
    }

    /**
     * @inheritDoc
     */
    public function convert(UploadedFile $file): array
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

    /**
     * @inheritDoc
     */
    public function getAllowedMimeTypes(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function canConvertMimetype(string $mimetype): bool
    {
        return false;
    }

}
