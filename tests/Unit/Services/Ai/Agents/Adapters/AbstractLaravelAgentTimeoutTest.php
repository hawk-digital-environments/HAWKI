<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Adapters;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractLaravelAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Agents\Values\AgentTimeouts;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Agents\Adapters\AbstractLaravelAgentTimeoutTest\AbstractLaravelAgentTimeoutTestFixtures\TimeoutExposingAgent;

#[CoversClass(AbstractLaravelAgent::class)]
#[CoversMethod(AbstractLaravelAgent::class, 'send')]
#[CoversMethod(AbstractLaravelAgent::class, 'sendStreaming')]
class AbstractLaravelAgentTimeoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAgentWithDriverConfig(array $driverConfig): TimeoutExposingAgent
    {
        $driver = new OpenAiCompatibleProvider($driverConfig, $this->createMock(Dispatcher::class));
        $provider = new AiProvider();
        $provider->provider_id = 'test';
        $proxy = new AiProviderProxy(
            $provider,
            $this->createMock(ProviderAdapterInterface::class),
            $driver
        );

        return new TimeoutExposingAgent(new AgentRequestContext(
            $proxy,
            new AiModel(['model_id' => 'gpt-4o']),
            new AiModelParameters()
        ));
    }

    // =========================================================================
    // send / sendStreaming
    // =========================================================================

    public function testSendPassesTheRequestTimeout(): void
    {
        $sut = $this->makeAgentWithDriverConfig(['request_timeout' => 300, 'stall_timeout' => 45]);

        $sut->send();

        static::assertSame(300, $sut->promptTimeout);
    }

    public function testSendStreamingPassesTheStallTimeout(): void
    {
        $sut = $this->makeAgentWithDriverConfig(['request_timeout' => 300, 'stall_timeout' => 45]);

        $sut->sendStreaming();

        // Streams run through Guzzle's stream handler, where the timeout limits the silence per read
        static::assertSame(45, $sut->streamTimeout);
    }

    public function testItFallsBackToTheDefaultsWithoutDriverConfig(): void
    {
        $sut = $this->makeAgentWithDriverConfig([]);

        $sut->send();
        $sut->sendStreaming();

        static::assertSame(AgentTimeouts::DEFAULT_REQUEST_TIMEOUT, $sut->promptTimeout);
        static::assertSame(AgentTimeouts::DEFAULT_STALL_TIMEOUT, $sut->streamTimeout);
    }

    public function testZeroDisablesBothTimeouts(): void
    {
        $sut = $this->makeAgentWithDriverConfig(['request_timeout' => 0, 'stall_timeout' => 0]);

        $sut->send();
        $sut->sendStreaming();

        static::assertSame(0, $sut->promptTimeout);
        static::assertSame(AgentTimeouts::DISABLED_STALL_READ_TIMEOUT, $sut->streamTimeout);
    }
}
