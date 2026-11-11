<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\StallAware;

use App\Services\Ai\Providers\StallAware\Concerns\AppliesStallDetection;
use App\Services\Ai\Providers\StallAware\StallAwareOllamaGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\OllamaProvider;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Providers\StallAware\StallAwareOllamaGatewayTest\StallAwareOllamaGatewayTestFixtures\ExposingStallAwareGateway;

#[CoversClass(StallAwareOllamaGateway::class)]
#[CoversTrait(AppliesStallDetection::class)]
class StallAwareOllamaGatewayTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(array $driverConfig): Provider
    {
        return new OllamaProvider(
            array_merge([
                'name' => 'test',
                'driver' => 'ollama',
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
