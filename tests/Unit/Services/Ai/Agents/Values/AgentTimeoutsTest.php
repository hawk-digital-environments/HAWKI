<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Values;

use App\Services\Ai\Agents\Values\AgentTimeouts;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Agents\Values\AgentTimeoutsTest\AgentTimeoutsTestFixtures\CurlOnlyAgentTimeouts;

#[CoversClass(AgentTimeouts::class)]
class AgentTimeoutsTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(AgentTimeouts::class, new AgentTimeouts());
    }

    // =========================================================================
    // fromDriverConfig
    // =========================================================================

    public function testItReadsBothTimeoutsFromTheDriverConfig(): void
    {
        $sut = AgentTimeouts::fromDriverConfig(['request_timeout' => 300, 'stall_timeout' => 45]);

        static::assertSame(300, $sut->forRequest());
        static::assertSame(45, $sut->forStream());
    }

    public function testItDefaultsToTheRequestTimeoutAndDisabledStallWithoutConfig(): void
    {
        $sut = AgentTimeouts::fromDriverConfig([]);

        static::assertSame(60, $sut->forRequest());
        // no stall timeout configured: streams get no idle limit instead of the package's 60 seconds
        static::assertSame(AgentTimeouts::DISABLED_STALL_READ_TIMEOUT, $sut->forStream());
    }

    public function testItLetsZeroRemoveTheRequestLimitAndDisableStall(): void
    {
        $sut = AgentTimeouts::fromDriverConfig(['request_timeout' => 0, 'stall_timeout' => 0]);

        static::assertSame(0, $sut->forRequest());
        static::assertSame(AgentTimeouts::DISABLED_STALL_READ_TIMEOUT, $sut->forStream());
    }

    public function testItFallsBackToTheDefaultsForInvalidValues(): void
    {
        $sut = AgentTimeouts::fromDriverConfig([
            'request_timeout' => '',
            'stall_timeout' => 'abc',
        ]);

        static::assertSame(60, $sut->forRequest());
        static::assertSame(AgentTimeouts::DISABLED_STALL_READ_TIMEOUT, $sut->forStream());
    }

    public function testItAcceptsNumericStringValues(): void
    {
        $sut = AgentTimeouts::fromDriverConfig(['request_timeout' => '300', 'stall_timeout' => '120']);

        static::assertSame(300, $sut->forRequest());
        static::assertSame(120, $sut->forStream());
    }

    // =========================================================================
    // Streams without the PHP stream handler (allow_url_fopen off)
    // =========================================================================

    public function testItDisablesThePromptTimeoutForStreamsWhenGuzzleUsesCurl(): void
    {
        // Without the stream handler, `timeout` would be a hard total limit for the whole
        // stream on curl — the prompt timeout must be disabled; stall detection then runs
        // through the curl low-speed options of the stall-aware gateways.
        $sut = new CurlOnlyAgentTimeouts(0, 45);

        static::assertSame(0, $sut->forStream());
    }

    public function testItAlsoDisablesThePromptTimeoutForCurlStreamsWithoutStallDetection(): void
    {
        $sut = new CurlOnlyAgentTimeouts(0, 0);

        static::assertSame(0, $sut->forStream());
    }
}
