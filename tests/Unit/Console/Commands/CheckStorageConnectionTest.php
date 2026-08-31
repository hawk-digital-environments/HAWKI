<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\CheckStorageConnection;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Concerns\CreatesFilesystemMocks;
use Tests\TestCase;

#[CoversClass(CheckStorageConnection::class)]
class CheckStorageConnectionTest extends TestCase
{
    use CreatesFilesystemMocks;

    /**
     * Disk names the bound factory was asked for, in call order.
     *
     * @var list<string>
     */
    private array $resolvedDisks = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the storage roles to their defaults so assertions do not depend on the test env.
        config([
            'filesystems.default' => 'local',
            'filesystems.file_storage' => 'local_file_storage',
            'filesystems.avatar_storage' => 'public',
        ]);

        // Warm up the console application: bootstrapping it resolves every registered command
        // through the container, and avatar-related commands resolve the avatar storage disk.
        // Doing this before the factory mock is bound keeps the call log below unpolluted.
        Artisan::call('list');
    }

    // =========================================================================
    // Default run: the three storage roles
    // =========================================================================

    public function testItChecksTheThreeStorageRolesByDefault(): void
    {
        $this->bindFactoryWithPassingDisk();

        $exitCode = Artisan::call('check:storage');
        $output = Artisan::output();

        static::assertSame(0, $exitCode);
        static::assertSame(['local', 'local_file_storage', 'public'], $this->resolvedDisks);

        static::assertStringContainsString('Framework default', $output);
        static::assertStringContainsString('File storage', $output);
        static::assertStringContainsString('Avatar storage', $output);
        static::assertStringContainsString('3 of 3 storage disks passed', $output);
    }

    public function testItChecksADiskSharedByMultipleRolesOnlyOnce(): void
    {
        config(['filesystems.default' => 'local_file_storage']);
        $this->bindFactoryWithPassingDisk();

        $exitCode = Artisan::call('check:storage');
        $output = Artisan::output();

        static::assertSame(0, $exitCode);
        // The shared disk is round-tripped once, with both roles grouped into a single row.
        static::assertSame(['local_file_storage', 'public'], $this->resolvedDisks);
        static::assertStringContainsString('Framework default, File storage', $output);
        static::assertStringContainsString('2 of 2 storage disks passed', $output);
    }

    // =========================================================================
    // --filesystem filter
    // =========================================================================

    public function testItChecksOnlyTheExplicitlyRequestedDisks(): void
    {
        $this->bindFactoryWithPassingDisk();

        $exitCode = Artisan::call('check:storage', ['--filesystem' => 'local']);
        $output = Artisan::output();

        static::assertSame(0, $exitCode);
        static::assertSame(['local'], $this->resolvedDisks);
        static::assertStringNotContainsString('Avatar storage', $output);
        static::assertStringContainsString('1 of 1 storage disks passed', $output);
    }

    public function testItFailsForUnknownExplicitlyRequestedDisks(): void
    {
        $this->bindFactoryWithPassingDisk();

        $exitCode = Artisan::call('check:storage', ['--filesystem' => 'bogus']);
        $output = Artisan::output();

        static::assertSame(1, $exitCode);
        static::assertStringContainsString('Unknown disk(s): bogus', $output);
        static::assertStringContainsString('Available disks:', $output);
    }

    // =========================================================================
    // Failure reporting
    // =========================================================================

    public function testItReportsARolePointingAtAnUnknownDiskAsFailure(): void
    {
        config(['filesystems.avatar_storage' => 'bogus']);
        $this->bindFactoryWithPassingDisk();

        $exitCode = Artisan::call('check:storage');
        $output = Artisan::output();

        static::assertSame(1, $exitCode);
        static::assertStringContainsString('FAIL', $output);
        static::assertStringContainsString('not configured in filesystems.disks', $output);
    }

    public function testItExitsNonZeroWhenADiskFailsTheWriteTest(): void
    {
        $this->bindFactoryWithFailingDisk();

        $exitCode = Artisan::call('check:storage');
        $output = Artisan::output();

        static::assertSame(1, $exitCode);
        static::assertStringContainsString('Upload failed', $output);
        static::assertStringContainsString('failed the connection test', $output);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function bindFactoryWithPassingDisk(): void
    {
        $written = [];
        $disk = $this->mockFilesystem();
        $disk->method('files')->willReturn([]);
        $disk->method('put')->willReturnCallback(
            static function (string $path, string $contents) use (&$written): bool {
                $written[$path] = $contents;

                return true;
            }
        );
        $disk->method('exists')->willReturn(true);
        $disk->method('get')->willReturnCallback(
            static function (string $path) use (&$written): string|null {
                return $written[$path] ?? null;
            }
        );
        $disk->method('delete')->willReturn(true);

        $this->bindFactory($disk);
    }

    private function bindFactoryWithFailingDisk(): void
    {
        $disk = $this->mockFilesystem();
        $disk->method('files')->willReturn([]);
        $disk->method('put')->willReturn(false);

        $this->bindFactory($disk);
    }

    private function bindFactory(Filesystem&MockObject $disk): void
    {
        $factory = $this->createMock(FilesystemFactory::class);
        $factory->method('disk')->willReturnCallback(
            function (string|null $name) use ($disk): Filesystem {
                $this->resolvedDisks[] = (string) $name;

                return $disk;
            }
        );

        $this->app->instance('filesystem', $factory);
    }
}
