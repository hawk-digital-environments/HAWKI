<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ConfigFileSync;

use App\Services\Ai\ConfigFileSync\ConfigFileSyncer;
use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ConfigFileSync\SyncActionDetector;
use App\Utils\JobMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\TestCase;

#[CoversClass(ConfigFileSyncer::class)]
class ConfigFileSyncerTest extends TestCase
{
    private SyncActionDetector&MockObject $detector;
    private NullLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        JobMetrics::resetCounter();
        $this->detector = $this->createMock(SyncActionDetector::class);
        $this->logger = new NullLogger();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSyncer(): ConfigSyncerInterface&MockObject
    {
        return $this->createMock(ConfigSyncerInterface::class);
    }

    private function makeSut(array $syncers): ConfigFileSyncer
    {
        return new ConfigFileSyncer($syncers, $this->detector, $this->logger);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut([]);
        static::assertInstanceOf(ConfigFileSyncer::class, $sut);
    }

    // =========================================================================
    // shouldSync check
    // =========================================================================

    public function testItReturnsNullWhenDetectorSaysNoSyncNeeded(): void
    {
        $this->detector->method('shouldSync')->willReturn(false);

        $sut = $this->makeSut([$this->makeSyncer()]);

        static::assertNull($sut->sync());
    }

    public function testItDoesNotCallSyncersWhenDetectorSaysNoSyncNeeded(): void
    {
        $this->detector->method('shouldSync')->willReturn(false);

        $syncer = $this->makeSyncer();
        $syncer->expects(static::never())->method('sync');

        $this->makeSut([$syncer])->sync();
    }

    // =========================================================================
    // force flag
    // =========================================================================

    public function testItBypassesDetectorWhenForceIsTrue(): void
    {
        // shouldSync is never called when force=true
        $this->detector->expects(static::never())->method('shouldSync');

        $syncer = $this->makeSyncer();
        $syncer->expects(static::once())->method('sync');

        $this->makeSut([$syncer])->sync(force: true);
    }

    // =========================================================================
    // Successful sync
    // =========================================================================

    public function testItReturnsJobMetricsAfterSuccessfulSync(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);

        $sut = $this->makeSut([$this->makeSyncer()]);

        static::assertInstanceOf(JobMetrics::class, $sut->sync());
    }

    public function testItCallsEachSyncerOnce(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);

        $syncerA = $this->makeSyncer();
        $syncerA->expects(static::once())->method('sync');

        $syncerB = $this->makeSyncer();
        $syncerB->expects(static::once())->method('sync');

        $this->makeSut([$syncerA, $syncerB])->sync();
    }

    public function testItCallsMarkAsSyncedAfterAllSyncersRun(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);
        $this->detector->expects(static::once())->method('markAsSynced');

        $this->makeSut([$this->makeSyncer()])->sync();
    }

    // =========================================================================
    // Syncer error isolation
    // =========================================================================

    public function testItContinuesWithRemainingsSyncersWhenOneThrows(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);

        $failingSyncer = $this->makeSyncer();
        $failingSyncer->method('sync')->willThrowException(new \RuntimeException('DB error'));

        $successSyncer = $this->makeSyncer();
        $successSyncer->expects(static::once())->method('sync');

        $this->makeSut([$failingSyncer, $successSyncer])->sync();
    }

    public function testItRecordsErrorInMetricsWhenSyncerThrows(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);

        $failingSyncer = $this->makeSyncer();
        $failingSyncer->method('sync')->willThrowException(new \RuntimeException('Something went wrong'));

        $metrics = $this->makeSut([$failingSyncer])->sync();

        static::assertTrue($metrics->hasErrors());
    }

    public function testItStillCallsMarkAsSyncedEvenWhenASyncerThrows(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);
        $this->detector->expects(static::once())->method('markAsSynced');

        $failingSyncer = $this->makeSyncer();
        $failingSyncer->method('sync')->willThrowException(new \RuntimeException('oops'));

        $this->makeSut([$failingSyncer])->sync();
    }

    // =========================================================================
    // Empty syncer list
    // =========================================================================

    public function testItReturnsMetricsWithNoErrorsWhenNoSyncersAreRegistered(): void
    {
        $this->detector->method('shouldSync')->willReturn(true);

        $metrics = $this->makeSut([])->sync();

        static::assertNotNull($metrics);
        static::assertFalse($metrics->hasErrors());
    }
}
