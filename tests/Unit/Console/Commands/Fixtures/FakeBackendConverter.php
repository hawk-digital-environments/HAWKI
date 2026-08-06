<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Fixtures;

use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;

/**
 * Minimal non-decorating converter used to simulate a concrete backend
 * (e.g. KreuzbergConverter) in command tests. Its MIME types, availability,
 * and class FQCN are fully controllable.
 */
class FakeBackendConverter implements FileConverterInterface
{
    public function __construct(
        private readonly array $mimeTypes = [],
        private readonly bool $available = true,
    ) {
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
        return $this->mimeTypes;
    }

    public function canConvertMimetype(string $mimetype): bool
    {
        return \in_array($mimetype, $this->mimeTypes, true);
    }

    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool
    {
        return false;
    }
}
