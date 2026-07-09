<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Migrations\Make;

use App\Services\Frontend\Migrations\Make\BackendMigrationCreator;
use App\Services\Frontend\Migrations\Make\FrontendMigrationCreator;
use App\Services\Frontend\Migrations\Make\JsMigrationCreator;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Tests\TestCase;

#[CoversClass(FrontendMigrationCreator::class)]
class FrontendMigrationCreatorTest extends TestCase
{
    // =========================================================================
    // Constants
    // =========================================================================

    public function testItDefinesRunTypeAfterLogin(): void
    {
        static::assertSame('after_login', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
    }

    public function testItDefinesRunTypeAfterPasskey(): void
    {
        static::assertSame('after_passkey', FrontendMigrationCreator::RUN_TYPE_AFTER_PASSKEY);
    }

    // =========================================================================
    // create
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(FrontendMigrationCreator::class, $sut);
    }

    public function testItReturnsTwoFilePaths(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertCount(2, $result);
    }

    public function testItReturnsPhpPathAsFirstElement(): void
    {
        $sut = $this->makeSut();
        [$phpPath] = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertStringEndsWith('.php', $phpPath);
    }

    public function testItReturnsTsPathAsSecondElement(): void
    {
        $sut = $this->makeSut();
        [, $tsPath] = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertStringEndsWith('.ts', $tsPath);
    }

    public function testItIncludesTimestampInMigrationName(): void
    {
        $sut = $this->makeSut(new \DateTimeImmutable('2024-01-15 12:00:00'));
        [$phpPath] = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertStringContainsString('2024_01_15_120000', $phpPath);
    }

    public function testItIncludesRunTypeInMigrationName(): void
    {
        $sut = $this->makeSut();
        [$phpPath] = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertStringContainsString('after_login', $phpPath);
    }

    public function testItIncludesNameInMigrationName(): void
    {
        $sut = $this->makeSut();
        [$phpPath] = $sut->create('my_widget', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertStringContainsString('my_widget', $phpPath);
    }

    public function testItConvertsNameToSnakeCase(): void
    {
        $sut = $this->makeSut();
        [$phpPath] = $sut->create('myWidget', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
        static::assertStringContainsString('my_widget', $phpPath);
    }

    public function testItConvertsCamelCaseRunTypeToSnakeCase(): void
    {
        $sut = $this->makeSut();
        [$phpPath] = $sut->create('test_migration', 'afterLogin');
        static::assertStringContainsString('after_login', $phpPath);
    }

    public function testJsMigrationGoesIntoRunTypeSubfolder(): void
    {
        $sut = $this->makeSut();
        [, $tsPath] = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_PASSKEY);
        static::assertStringContainsString('after_passkey', $tsPath);
    }

    public function testBothFilesShareTheSameMigrationName(): void
    {
        $sut = $this->makeSut(new \DateTimeImmutable('2024-06-01 09:30:00'));
        [$phpPath, $tsPath] = $sut->create('update_user', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);

        $phpBasename = pathinfo($phpPath, PATHINFO_FILENAME);
        $tsBasename = pathinfo($tsPath, PATHINFO_FILENAME);
        static::assertSame($phpBasename, $tsBasename);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(?\DateTimeImmutable $now = null): FrontendMigrationCreator
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($now ?? new \DateTimeImmutable('2024-01-01 00:00:00'));

        return new FrontendMigrationCreator(
            phpMigrationCreator: new BackendMigrationCreator($files),
            jsMigrationCreator: new JsMigrationCreator($files),
            clock: $clock
        );
    }
}
