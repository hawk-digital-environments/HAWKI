<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Health\Value;

use App\Services\System\Health\Value\HealthTimerStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HealthTimerStorage::class)]
class HealthTimerStorageTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'hawki_health_test_');
        // Start with a clean, non-existing file so the storage initialises with defaults.
        @unlink($this->tempFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempFile);
        parent::tearDown();
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);

        static::assertInstanceOf(HealthTimerStorage::class, $sut);
    }

    public function testItConstructsWithNullAndUsesDefaultPath(): void
    {
        $sut = new HealthTimerStorage(null);

        static::assertInstanceOf(HealthTimerStorage::class, $sut);
    }

    // =========================================================================
    // Defaults (no file)
    // =========================================================================

    public function testItHasLastExecutionFailedFalseByDefault(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);

        static::assertFalse($sut->hasLastExecutionFailed());
    }

    public function testItHasQuickTestCounterZeroByDefault(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);

        static::assertSame(0, $sut->getQuickTestCounter());
    }

    // =========================================================================
    // markAsFailed / hasLastExecutionFailed
    // =========================================================================

    public function testItMarkAsFailedSetsFailedState(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->markAsFailed();

        static::assertTrue($sut->hasLastExecutionFailed());
    }

    public function testItMarkAsFailedIsPersisted(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->markAsFailed();

        // New instance reads from disk.
        $reloaded = new HealthTimerStorage($this->tempFile);
        static::assertTrue($reloaded->hasLastExecutionFailed());
    }

    public function testItMarkAsFailedIsIdempotent(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->markAsFailed();
        $sut->markAsFailed();

        static::assertTrue($sut->hasLastExecutionFailed());
    }

    // =========================================================================
    // markAsHealthy / hasLastExecutionFailed
    // =========================================================================

    public function testItMarkAsHealthyClearsFailedState(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->markAsFailed();
        $sut->markAsHealthy();

        static::assertFalse($sut->hasLastExecutionFailed());
    }

    public function testItMarkAsHealthyIsPersisted(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->markAsFailed();
        $sut->markAsHealthy();

        $reloaded = new HealthTimerStorage($this->tempFile);
        static::assertFalse($reloaded->hasLastExecutionFailed());
    }

    public function testItMarkAsHealthyIsIdempotent(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->markAsHealthy();
        $sut->markAsHealthy();

        static::assertFalse($sut->hasLastExecutionFailed());
    }

    // =========================================================================
    // increaseCounter / getQuickTestCounter
    // =========================================================================

    public function testItIncreaseCounterIncrementsValue(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);

        $sut->increaseCounter();

        static::assertSame(1, $sut->getQuickTestCounter());
    }

    public function testItIncreaseCounterReturnsNewValue(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);

        $result = $sut->increaseCounter();

        static::assertSame(1, $result);
    }

    public function testItIncreaseCounterAccumulates(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);

        $sut->increaseCounter();
        $sut->increaseCounter();
        $sut->increaseCounter();

        static::assertSame(3, $sut->getQuickTestCounter());
    }

    public function testItIncreaseCounterIsPersisted(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->increaseCounter();
        $sut->increaseCounter();

        $reloaded = new HealthTimerStorage($this->tempFile);
        static::assertSame(2, $reloaded->getQuickTestCounter());
    }

    // =========================================================================
    // resetCounter
    // =========================================================================

    public function testItResetCounterSetsCounterToZero(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->increaseCounter();
        $sut->increaseCounter();
        $sut->resetCounter();

        static::assertSame(0, $sut->getQuickTestCounter());
    }

    public function testItResetCounterIsPersisted(): void
    {
        $sut = new HealthTimerStorage($this->tempFile);
        $sut->increaseCounter();
        $sut->resetCounter();

        $reloaded = new HealthTimerStorage($this->tempFile);
        static::assertSame(0, $reloaded->getQuickTestCounter());
    }

    // =========================================================================
    // Graceful handling of corrupt / missing file
    // =========================================================================

    public function testItHandlesMissingFileGracefully(): void
    {
        $sut = new HealthTimerStorage('/tmp/non_existent_hawki_test_file_xyz.json');

        static::assertFalse($sut->hasLastExecutionFailed());
        static::assertSame(0, $sut->getQuickTestCounter());
    }

    public function testItHandlesCorruptJsonGracefully(): void
    {
        file_put_contents($this->tempFile, 'this is not json');

        $sut = new HealthTimerStorage($this->tempFile);

        static::assertFalse($sut->hasLastExecutionFailed());
        static::assertSame(0, $sut->getQuickTestCounter());
    }

    public function testItHandlesNonArrayJsonGracefully(): void
    {
        file_put_contents($this->tempFile, '"just a string"');

        $sut = new HealthTimerStorage($this->tempFile);

        static::assertFalse($sut->hasLastExecutionFailed());
        static::assertSame(0, $sut->getQuickTestCounter());
    }
}
