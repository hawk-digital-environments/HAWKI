<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Fixtures;

use App\Services\FileConverter\Interfaces\FileConverterExtensionInterface;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;

/**
 * Decorating converter used in command tests. Implements the real
 * {@see FileConverterExtensionInterface} so the command's chain walker
 * descends into it. Its reported MIME types mirror a real decorator:
 * the union of its own contributions and the inner converter's types.
 */
class FakeWrappingConverter implements FileConverterExtensionInterface
{
    public function __construct(
        private readonly FileConverterInterface $inner,
        private readonly array $ownMimeTypes = [],
        private readonly bool $available = true,
    ) {
    }

    public function getInnerConverter(): FileConverterInterface
    {
        return $this->inner;
    }

    public static function isValidConfig(array $config): bool
    {
        return true;
    }

    public function setConfig(array $config): void
    {
    }

    public function convert(FileReference $file): FileCollection
    {
        return new FileCollection();
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getAllowedMimeTypes(): array
    {
        return array_values(array_unique(array_merge(
            $this->ownMimeTypes,
            $this->inner->getAllowedMimeTypes(),
        )));
    }

    public function canConvertMimetype(string $mimetype): bool
    {
        return \in_array($mimetype, $this->getAllowedMimeTypes(), true);
    }

    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool
    {
        return false;
    }
}
