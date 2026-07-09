<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ConfigFileSync;

use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\ConfigFileSync\SyncActionDetector;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(SyncActionDetector::class)]
class SyncActionDetectorTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /** Returns an in-memory cache repository that survives the lifetime of the test. */
    private function makeCache(): Repository
    {
        return new Repository(new ArrayStore());
    }

    /**
     * Returns a mock syncer whose getCurrentHash() returns the given string.
     */
    private function makeSyncer(string $hash): ConfigSyncerInterface&MockObject
    {
        $syncer = $this->createMock(ConfigSyncerInterface::class);
        $syncer->method('getCurrentHash')->willReturn($hash);
        return $syncer;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new SyncActionDetector($this->makeCache());
        static::assertInstanceOf(SyncActionDetector::class, $sut);
    }

    // =========================================================================
    // shouldSync — fresh cache (no previous sync marker)
    // =========================================================================

    public function testItShouldSyncReturnsTrueWhenNoPreviousSyncExists(): void
    {
        $sut = new SyncActionDetector($this->makeCache());

        static::assertTrue($sut->shouldSync([$this->makeSyncer('abc')]));
    }

    // =========================================================================
    // shouldSync — after markAsSynced
    // =========================================================================

    public function testItShouldSyncReturnsFalseAfterMarkAsSynced(): void
    {
        $cache = $this->makeCache();
        $sut = new SyncActionDetector($cache);
        $syncers = [$this->makeSyncer('abc123')];

        $sut->markAsSynced($syncers);

        static::assertFalse($sut->shouldSync($syncers));
    }

    public function testItShouldSyncReturnsTrueWhenHashChangesAfterMarkAsSynced(): void
    {
        $cache = $this->makeCache();
        $sut = new SyncActionDetector($cache);

        $sut->markAsSynced([$this->makeSyncer('old-hash')]);

        static::assertTrue($sut->shouldSync([$this->makeSyncer('new-hash')]));
    }

    // =========================================================================
    // shouldSync — hash combines all syncers
    // =========================================================================

    public function testItShouldSyncConsidersAllSyncersInHash(): void
    {
        $cache = $this->makeCache();
        $sut = new SyncActionDetector($cache);

        $syncerA = $this->makeSyncer('hash-a');
        $syncerB = $this->makeSyncer('hash-b');

        $sut->markAsSynced([$syncerA, $syncerB]);

        // Changing only syncer B's hash must trigger a new sync
        $syncerBChanged = $this->makeSyncer('hash-b-modified');
        static::assertTrue($sut->shouldSync([$syncerA, $syncerBChanged]));
    }

    // =========================================================================
    // clearSyncMarker
    // =========================================================================

    public function testItClearSyncMarkerForcesNextShouldSyncToReturnTrue(): void
    {
        $cache = $this->makeCache();
        $sut = new SyncActionDetector($cache);
        $syncers = [$this->makeSyncer('abc')];

        $sut->markAsSynced($syncers);
        static::assertFalse($sut->shouldSync($syncers), 'Precondition: synced state must be false before clearing');

        $sut->clearSyncMarker();

        static::assertTrue($sut->shouldSync($syncers));
    }

    // =========================================================================
    // markAsSynced — idempotency
    // =========================================================================

    public function testItMarkAsSyncedIsIdempotent(): void
    {
        $cache = $this->makeCache();
        $sut = new SyncActionDetector($cache);
        $syncers = [$this->makeSyncer('stable-hash')];

        $sut->markAsSynced($syncers);
        $sut->markAsSynced($syncers);

        static::assertFalse($sut->shouldSync($syncers));
    }
}
