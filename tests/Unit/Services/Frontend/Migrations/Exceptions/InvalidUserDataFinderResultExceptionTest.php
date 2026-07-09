<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Migrations\Exceptions;

use App\Services\Frontend\Migrations\Exceptions\FrontendMigrationExceptionInterface;
use App\Services\Frontend\Migrations\Exceptions\InvalidUserDataFinderResultException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidUserDataFinderResultException::class)]
class InvalidUserDataFinderResultExceptionTest extends TestCase
{
    // =========================================================================
    // forNonArrayReturnType
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = InvalidUserDataFinderResultException::forNonArrayReturnType('some_migration');
        static::assertInstanceOf(InvalidUserDataFinderResultException::class, $sut);
    }

    public function testItImplementsFrontendMigrationExceptionInterface(): void
    {
        $sut = InvalidUserDataFinderResultException::forNonArrayReturnType('some_migration');
        static::assertInstanceOf(FrontendMigrationExceptionInterface::class, $sut);
    }

    public function testItIsARuntimeException(): void
    {
        $sut = InvalidUserDataFinderResultException::forNonArrayReturnType('some_migration');
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItIncludesMigrationNameInMessage(): void
    {
        $sut = InvalidUserDataFinderResultException::forNonArrayReturnType('my_special_migration');
        static::assertStringContainsString('my_special_migration', $sut->getMessage());
    }

    public function testItMessageDescribesExpectedReturnType(): void
    {
        $sut = InvalidUserDataFinderResultException::forNonArrayReturnType('some_migration');
        static::assertStringContainsString('array', $sut->getMessage());
        static::assertStringContainsString('null', $sut->getMessage());
    }

    public function testItFormatsMessageCorrectly(): void
    {
        $migrationName = '2024_01_15_120000_after_login_test';
        $sut = InvalidUserDataFinderResultException::forNonArrayReturnType($migrationName);
        static::assertSame(
            sprintf('User data finder closure for migration "%s" must return an array or null/false.', $migrationName),
            $sut->getMessage()
        );
    }
}
