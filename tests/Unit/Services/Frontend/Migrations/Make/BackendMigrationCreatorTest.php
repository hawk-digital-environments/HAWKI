<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Migrations\Make;

use App\Services\Frontend\Migrations\Make\BackendMigrationCreator;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BackendMigrationCreator::class)]
class BackendMigrationCreatorTest extends TestCase
{
    // =========================================================================
    // create
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new BackendMigrationCreator($this->createMock(Filesystem::class));
        static::assertInstanceOf(BackendMigrationCreator::class, $sut);
    }

    public function testItReturnsAbsoluteFilePath(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $sut = new BackendMigrationCreator($files);
        $result = $sut->create('2024_01_15_120000_after_login_test', '/tmp/migrations');
        static::assertSame('/tmp/migrations/2024_01_15_120000_after_login_test.php', $result);
    }

    public function testItAppendsPhpExtension(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $sut = new BackendMigrationCreator($files);
        $result = $sut->create('my_migration', '/some/path');
        static::assertStringEndsWith('.php', $result);
    }

    public function testItEnsuresDirectoryExists(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');
        $files->expects($this->once())
            ->method('ensureDirectoryExists')
            ->with('/tmp/migrations');

        $sut = new BackendMigrationCreator($files);
        $sut->create('test_migration', '/tmp/migrations');
    }

    public function testItWritesStubContentToFile(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('<?php // stub content');
        $files->expects($this->once())
            ->method('put')
            ->with(
                '/tmp/migrations/test_migration.php',
                '<?php // stub content'
            );

        $sut = new BackendMigrationCreator($files);
        $sut->create('test_migration', '/tmp/migrations');
    }

    public function testItReadsFromTheBackendStubFile(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $capturedStubPath = null;
        $files->method('get')
            ->with($this->callback(function (string $path) use (&$capturedStubPath): bool {
                $capturedStubPath = $path;
                return true;
            }))
            ->willReturn('');

        $sut = new BackendMigrationCreator($files);
        $sut->create('test_migration', '/tmp/migrations');

        static::assertStringEndsWith('backend_migration.stub', $capturedStubPath);
    }
}
