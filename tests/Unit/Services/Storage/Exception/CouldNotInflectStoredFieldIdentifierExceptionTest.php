<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Exception;

use App\Services\Storage\Exception\CouldNotInflectStoredFieldIdentifierException;
use App\Services\Storage\Exception\StorageExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CouldNotInflectStoredFieldIdentifierException::class)]
class CouldNotInflectStoredFieldIdentifierExceptionTest extends TestCase
{
    // =========================================================================
    // Type hierarchy
    // =========================================================================

    public function testItImplementsStorageExceptionInterface(): void
    {
        $sut = new CouldNotInflectStoredFieldIdentifierException('some reason');

        static::assertInstanceOf(StorageExceptionInterface::class, $sut);
    }

    public function testItExtendsValueError(): void
    {
        $sut = new CouldNotInflectStoredFieldIdentifierException('some reason');

        static::assertInstanceOf(\ValueError::class, $sut);
    }

    // =========================================================================
    // Message
    // =========================================================================

    public function testItIncludesReasonInMessage(): void
    {
        $sut = new CouldNotInflectStoredFieldIdentifierException('User with id 42 does not have a uuid set.');

        static::assertSame(
            'Could not inflect stored field identifier: User with id 42 does not have a uuid set.',
            $sut->getMessage()
        );
    }
}
