<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Adapters;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractLaravelAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Unit\Services\Ai\Agents\Adapters\AbstractLaravelAgentTimeoutTest\AbstractLaravelAgentTimeoutTestFixtures\TimeoutExposingAgent;
use Tests\TestCase;

#[CoversClass(AbstractLaravelAgent::class)]
#[CoversMethod(AbstractLaravelAgent::class, 'timeout')]
class AbstractLaravelAgentTimeoutTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAgentWithDriverConfig(array $driverConfig): TimeoutExposingAgent
    {
        $driver = new OpenAiCompatibleProvider($driverConfig, $this->createMock(Dispatcher::class));
        $proxy = new AiProviderProxy(
            new AiProvider(),
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
    // timeout / getStallSeconds
    // =========================================================================

    public function testItUsesTheTimeoutFromTheProviderDriverConfig(): void
    {
        $sut = $this->makeAgentWithDriverConfig(['request_timeout' => 300, 'stall_timeout' => 45]);

        static::assertSame(300, $sut->exposeTimeout());
        static::assertSame(45, $sut->exposeStallSeconds());
    }

    public function testItFallsBackToThePackageDefaultWithoutDriverConfig(): void
    {
        $sut = $this->makeAgentWithDriverConfig([]);

        static::assertSame(60, $sut->exposeTimeout());
        static::assertNull($sut->exposeStallSeconds());
    }

    public function testItLetsZeroDisableTheTimeout(): void
    {
        $sut = $this->makeAgentWithDriverConfig(['request_timeout' => 0, 'stall_timeout' => 0]);

        static::assertSame(0, $sut->exposeTimeout());
        // 0 stays 0 (disabled), it is not turned into null
        static::assertSame(0, $sut->exposeStallSeconds());
    }
}
