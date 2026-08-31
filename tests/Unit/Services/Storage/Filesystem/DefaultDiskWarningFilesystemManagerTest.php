<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage\Filesystem;

use App\Services\Storage\Filesystem\DefaultDiskWarningFilesystemManager;
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
    // Helpers
    // =========================================================================

    private function makeSut(LoggerInterface $logger): DefaultDiskWarningFilesystemManager
    {
        return new DefaultDiskWarningFilesystemManager($this->app, $logger);
    }

    private function mockLogger(): LoggerInterface&MockObject
    {
        return $this->createMock(LoggerInterface::class);
    }
}
