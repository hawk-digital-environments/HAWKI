<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\AbstractFileStorageTestFixtures;

use App\Services\Storage\AbstractFileStorage;
use App\Services\Storage\Values\StoredFileIdentifier;

/**
 * Minimal concrete subclass that exposes protected methods for testing.
 */
class ConcreteFileStorageStub extends AbstractFileStorage
{
    public function getAllowedMimeTypes(): array
    {
        return ['image/png', 'image/jpeg'];
    }

    public function exposeBuildFolder(StoredFileIdentifier $identifier, bool $temp = false): string
    {
        return $this->buildFolder($identifier, $temp);
    }

    public function exposeBuildPath(StoredFileIdentifier $identifier, string $filename, bool $temp = false): string
    {
        return $this->buildPath($identifier, $filename, $temp);
    }

    public function exposeFilterMimeTypesByAllowed(array $availableMimeTypes): array
    {
        return $this->filterMimeTypesByAllowed($availableMimeTypes);
    }
}
