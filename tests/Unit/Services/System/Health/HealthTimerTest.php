<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Health;

use App\Services\System\Health\HealthTimer;
use App\Services\System\Health\Value\HealthTimerStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HealthTimer::class)]
class HealthTimerTest extends TestCase
{
    private string $tempFile;
    private HealthTimerStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'hawki_timer_test_');
        @unlink($this->tempFile);
        $this->storage = new HealthTimerStorage($this->tempFile);
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
        $sut = new HealthTimer($this->storage);

        static::assertInstanceOf(HealthTimer::class, $sut);
    }

    public function testItConstructsWithDefaultStoragePath(): void
    {
        $sut = new HealthTimer();

        static::assertInstanceOf(HealthTimer::class, $sut);
    }

    // =========================================================================
    // getTestType — initial state
    // =========================================================================

    public function testItReturnsQuickOnFirstCall(): void
    {
        $sut = new HealthTimer($this->storage, 10);

        static::assertSame(HealthTimer::TEST_TYPE_QUICK, $sut->getTestType());
    }

    // =========================================================================
    // getTestType — counter threshold
    // =========================================================================

    public function testItReturnsDeepWhenCounterReachesThreshold(): void
    {
        $sut = new HealthTimer($this->storage, 3);

        // Three quick checks fill the counter.
        $sut->getTestType(); // counter: 1
        $sut->getTestType(); // counter: 2
        $sut->getTestType(); // counter: 3 -> deep + reset

        static::assertSame(HealthTimer::TEST_TYPE_DEEP, $sut->getTestType());
    }

    public function testItResetsCounterAfterDeepTestDueToThreshold(): void
    {
        $sut = new HealthTimer($this->storage, 2);

        $sut->getTestType(); // quick, counter: 1
        $sut->getTestType(); // quick, counter: 2 -> deep + reset
        $sut->getTestType(); // deep (triggered by counter)

        // Counter reset; next call should be quick again.
        static::assertSame(HealthTimer::TEST_TYPE_QUICK, $sut->getTestType());
    }

    // =========================================================================
    // getTestType — failure state
    // =========================================================================

    public function testItReturnsDeepAfterMarkAsFailed(): void
    {
        $sut = new HealthTimer($this->storage, 10);
        $sut->markAsFailed();

        static::assertSame(HealthTimer::TEST_TYPE_DEEP, $sut->getTestType());
    }

    public function testItContinuesToReturnDeepWhileFailedStateActive(): void
    {
        $sut = new HealthTimer($this->storage, 10);
        $sut->markAsFailed();

        static::assertSame(HealthTimer::TEST_TYPE_DEEP, $sut->getTestType());
        static::assertSame(HealthTimer::TEST_TYPE_DEEP, $sut->getTestType());
    }

    // =========================================================================
    // markAsFailed / markAsHealthy interaction
    // =========================================================================

    public function testItReturnsQuickAfterMarkAsHealthy(): void
    {
        $sut = new HealthTimer($this->storage, 10);
        $sut->markAsFailed();
        $sut->markAsHealthy();

        static::assertSame(HealthTimer::TEST_TYPE_QUICK, $sut->getTestType());
    }

    // =========================================================================
    // Constants
    // =========================================================================

    public function testItExposesQuickTestTypeConstant(): void
    {
        static::assertSame('quick', HealthTimer::TEST_TYPE_QUICK);
    }

    public function testItExposesDeepTestTypeConstant(): void
    {
        static::assertSame('deep', HealthTimer::TEST_TYPE_DEEP);
    }
}
