<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Migrations\Exceptions;

use App\Services\Frontend\Migrations\Exceptions\FrontendMigrationExceptionInterface;
use App\Services\Frontend\Migrations\Exceptions\NoDownForFrontendMigrationsExceptionException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NoDownForFrontendMigrationsExceptionException::class)]
class NoDownForFrontendMigrationsExceptionExceptionTest extends TestCase
{
    // =========================================================================
    // forMigration
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = NoDownForFrontendMigrationsExceptionException::forMigration('SomeMigration');
        static::assertInstanceOf(NoDownForFrontendMigrationsExceptionException::class, $sut);
    }

    public function testItImplementsFrontendMigrationExceptionInterface(): void
    {
        $sut = NoDownForFrontendMigrationsExceptionException::forMigration('SomeMigration');
        static::assertInstanceOf(FrontendMigrationExceptionInterface::class, $sut);
    }

    public function testItIsARuntimeException(): void
    {
        $sut = NoDownForFrontendMigrationsExceptionException::forMigration('SomeMigration');
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItIncludesMigrationNameInMessage(): void
    {
        $sut = NoDownForFrontendMigrationsExceptionException::forMigration('MySpecialMigration');
        static::assertStringContainsString('MySpecialMigration', $sut->getMessage());
    }

    public function testItFormatsMessageCorrectly(): void
    {
        $migrationName = '2024_01_15_120000_after_passkey_update';
        $sut = NoDownForFrontendMigrationsExceptionException::forMigration($migrationName);
        static::assertSame(
            sprintf(
                'The migration "%s" is migrating data, only accessible with the user\'s passkey. Therefore we can not provide a down migration for it. This migration is one way only. Sorry :(',
                $migrationName
            ),
            $sut->getMessage()
        );
    }
}
