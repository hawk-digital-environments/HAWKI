<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\StallAware;

use App\Services\Ai\Providers\StallAware\Concerns\AppliesStallDetection;
use App\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGatewayTest\StallAwareOpenAiCompatibleGatewayTestFixtures\ExposingStallAwareGateway;

#[CoversClass(StallAwareOpenAiCompatibleGateway::class)]
#[CoversTrait(AppliesStallDetection::class)]
class StallAwareOpenAiCompatibleGatewayTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(array $driverConfig): Provider
    {
        return new OpenAiCompatibleProvider(
            array_merge([
                'name' => 'test',
                'driver' => 'openai-compatible',
                'key' => 'test-key',
                'url' => 'https://api.example.com/v1',
            ], $driverConfig),
            $this->createMock(Dispatcher::class)
        );
    }

    private function makeSut(): ExposingStallAwareGateway
    {
        return new ExposingStallAwareGateway($this->createMock(Dispatcher::class));
    }

    // =========================================================================
    // Stall detection
    // =========================================================================

    public function testItAppliesTheStallTimeoutAsCurlLowSpeedOptions(): void
    {
        $client = $this->makeSut()->exposeClient($this->makeProvider(['stall_timeout' => 120]), 300);

        static::assertSame(1, $client->getOptions()['curl'][CURLOPT_LOW_SPEED_LIMIT]);
        static::assertSame(120, $client->getOptions()['curl'][CURLOPT_LOW_SPEED_TIME]);
        static::assertSame(120, $client->getOptions()['read_timeout']);
    }

    public function testItLeavesTheOtherClientOptionsUntouched(): void
    {
        $client = $this->makeSut()->exposeClient($this->makeProvider(['stall_timeout' => 45]), 300);

        static::assertSame(300, $client->getOptions()['timeout']);
        static::assertSame('Bearer test-key', $client->getOptions()['headers']['Authorization']);
    }

    public function testItSkipsStallDetectionWhenDisabled(): void
    {
        $client = $this->makeSut()->exposeClient($this->makeProvider(['stall_timeout' => 0]));

        static::assertArrayNotHasKey('curl', $client->getOptions());
        static::assertArrayNotHasKey('read_timeout', $client->getOptions());
    }

    public function testItSkipsStallDetectionWithoutAStallTimeout(): void
    {
        $client = $this->makeSut()->exposeClient($this->makeProvider([]));

        static::assertArrayNotHasKey('curl', $client->getOptions());
        static::assertArrayNotHasKey('read_timeout', $client->getOptions());
    }
}
