<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Exception;

use App\Services\Storage\Exception\InvalidStorageFileIdentifierStringGivenException;
use App\Services\Storage\Exception\StorageExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidStorageFileIdentifierStringGivenException::class)]
class InvalidStorageFileIdentifierStringGivenExceptionTest extends TestCase
{
    // =========================================================================
    // Type hierarchy
    // =========================================================================

    public function testItImplementsStorageExceptionInterface(): void
    {
        $sut = new InvalidStorageFileIdentifierStringGivenException('bad-input');

        static::assertInstanceOf(StorageExceptionInterface::class, $sut);
    }

    public function testItExtendsInvalidArgumentException(): void
    {
        $sut = new InvalidStorageFileIdentifierStringGivenException('bad-input');

        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    // =========================================================================
    // Message
    // =========================================================================

    public function testItIncludesTheInvalidIdInMessage(): void
    {
        $sut = new InvalidStorageFileIdentifierStringGivenException('not-valid');

        static::assertSame(
            "The given string 'not-valid' is not a valid storage file identifier. Expected format: 'category-uuid[.extension]'.",
            $sut->getMessage()
        );
    }
}
