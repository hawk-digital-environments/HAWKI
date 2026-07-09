<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Handlers\AbstractFileConverterTestFixtures;

use App\Services\FileConverter\Handlers\AbstractFileConverter;
use App\Services\Storage\Values\FileCollection;
use App\Services\Storage\Values\FileReference;

/**
 * Minimal concrete stub to make AbstractFileConverter testable.
 */
class ConcreteFileConverterStub extends AbstractFileConverter
{
    public function __construct(private readonly array $mimeTypes = ['text/plain', 'application/pdf'])
    {
    }

    public static function isValidConfig(array $config): bool
    {
        return true;
    }

    public function convert(FileReference $file): FileCollection
    {
        return new FileCollection();
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAllowedMimeTypes(): array
    {
        return $this->mimeTypes;
    }
}
