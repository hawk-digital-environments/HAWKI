<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Handlers;


use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Value\FileCollection;
use App\Services\Storage\Value\FileReference;

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
    public function convert(FileReference $file): FileCollection
    {
        return new FileCollection();
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

    /**
     * @inheritDoc
     */
    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool
    {
        return false;
    }
}
