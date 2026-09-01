<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Filesystem;

use App\Services\Storage\Filesystem\DefaultDiskWarningFilesystemManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(DefaultDiskWarningFilesystemManager::class)]
class DefaultDiskWarningFilesystemManagerTest extends TestCase
{
    // =========================================================================
    // Container wiring
    // =========================================================================

    public function testItIsBoundAsTheApplicationFilesystemManager(): void
    {
        static::assertInstanceOf(DefaultDiskWarningFilesystemManager::class, $this->app->get('filesystem'));
    }

    // =========================================================================
    // Default disk fallback warning
    // =========================================================================

    public function testItWarnsWhenTheDefaultDiskIsResolved(): void
    {
        $logger = $this->mockLogger();
        $logger->expects(static::once())->method('warning');

        $sut = $this->makeSut($logger);

        $sut->disk();
    }

    public function testItWarnsOnlyOnceWhenTheDefaultDiskIsResolvedRepeatedly(): void
    {
        $logger = $this->mockLogger();
        $logger->expects(static::once())->method('warning');

        $sut = $this->makeSut($logger);

        $sut->disk();
        $sut->disk();
    }

    public function testItDoesNotWarnForExplicitDiskNames(): void
    {
        $logger = $this->mockLogger();
        $logger->expects(static::never())->method('warning');

        $sut = $this->makeSut($logger);

        $sut->disk('local');
        $sut->disk('local_file_storage');
        $sut->drive('local');
    }

    // =========================================================================
    // Decoration state inheritance
    // =========================================================================

    public function testItKeepsCustomCreatorsRegisteredBeforeDecoration(): void
    {
        config(['filesystems.disks.custom_test_disk' => ['driver' => 'custom_test_disk']]);

        $baseManager = new FilesystemManager($this->app);
        $baseManager->extend('custom_test_disk', static function (): FilesystemAdapter {
            $adapter = new LocalFilesystemAdapter(sys_get_temp_dir());

            return new FilesystemAdapter(new FlysystemFilesystem($adapter), $adapter, []);
        });

        $sut = DefaultDiskWarningFilesystemManager::decorate($baseManager, $this->mockLogger());

        static::assertInstanceOf(FilesystemAdapter::class, $sut->disk('custom_test_disk'));
    }

    public function testItReusesDisksResolvedBeforeDecoration(): void
    {
        $baseManager = new FilesystemManager($this->app);
        $baseDisk = $baseManager->disk('local');

        $sut = DefaultDiskWarningFilesystemManager::decorate($baseManager, $this->mockLogger());

        static::assertSame($baseDisk, $sut->disk('local'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(LoggerInterface $logger): DefaultDiskWarningFilesystemManager
    {
        return DefaultDiskWarningFilesystemManager::decorate(new FilesystemManager($this->app), $logger);
    }

    private function mockLogger(): LoggerInterface&MockObject
    {
        return $this->createMock(LoggerInterface::class);
    }
}
