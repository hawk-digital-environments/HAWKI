<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Migrations\Make;

use App\Services\Frontend\Migrations\Make\JsMigrationCreator;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(JsMigrationCreator::class)]
class JsMigrationCreatorTest extends TestCase
{
    // =========================================================================
    // create
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new JsMigrationCreator($this->createMock(Filesystem::class));
        static::assertInstanceOf(JsMigrationCreator::class, $sut);
    }

    public function testItReturnsAbsoluteFilePath(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $sut = new JsMigrationCreator($files);
        $result = $sut->create('2024_01_15_120000_after_login_test', '/tmp/js/migrations');
        static::assertSame('/tmp/js/migrations/2024_01_15_120000_after_login_test.ts', $result);
    }

    public function testItAppendsTsExtension(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $sut = new JsMigrationCreator($files);
        $result = $sut->create('my_migration', '/some/path');
        static::assertStringEndsWith('.ts', $result);
    }

    public function testItEnsuresDirectoryExists(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');
        $files->expects($this->once())
            ->method('ensureDirectoryExists')
            ->with('/tmp/js/migrations');

        $sut = new JsMigrationCreator($files);
        $sut->create('test_migration', '/tmp/js/migrations');
    }

    public function testItWritesStubContentToFile(): void
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn("import type {MigrationContext} from '\$lib/data/migrations/migrator.js';");
        $files->expects($this->once())
            ->method('put')
            ->with(
                '/tmp/js/migrations/test_migration.ts',
                "import type {MigrationContext} from '\$lib/data/migrations/migrator.js';"
            );

        $sut = new JsMigrationCreator($files);
        $sut->create('test_migration', '/tmp/js/migrations');
    }

    public function testItReadsFromTheJsStubFile(): void
    {
        $files = $this->createMock(Filesystem::class);

        $capturedStubPath = null;
        $files->method('get')
            ->with($this->callback(function (string $path) use (&$capturedStubPath): bool {
                $capturedStubPath = $path;
                return true;
            }))
            ->willReturn('');

        $sut = new JsMigrationCreator($files);
        $sut->create('test_migration', '/tmp/js/migrations');

        static::assertStringEndsWith('js_migration.stub', $capturedStubPath);
    }
}
