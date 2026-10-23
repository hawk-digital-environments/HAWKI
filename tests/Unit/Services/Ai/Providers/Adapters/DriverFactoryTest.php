<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters;

use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\ExtendedAiManager;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\ProviderSettings;
use App\Services\System\Container\ServiceLocator;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(DriverFactory::class)]
class DriverFactoryTest extends TestCase
{
    private ExtendedAiManager&MockObject $manager;
    private ServiceLocator&MockObject $serviceLocator;

    /** @var array<string, mixed>|null The driver config the manager received. */
    private array|null $capturedConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->createMock(ExtendedAiManager::class);
        $this->manager->method('instanceWithConfig')->willReturnCallback(
            function (string $name, array $config) {
                $this->capturedConfig = $config;
                return new OpenAiCompatibleProvider($config, $this->createMock(Dispatcher::class));
            }
        );
        $this->serviceLocator = $this->createMock(ServiceLocator::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(array $adapterSettings = []): DriverFactory
    {
        $provider = new AiProvider();
        $provider->provider_id = 'test';
        $provider->settings = ProviderSettings::fromArray(['adapter' => $adapterSettings]);

        return new DriverFactory($this->manager, $this->serviceLocator, $provider, 60, 120);
    }

    // =========================================================================
    // Timeout defaults
    // =========================================================================

    public function testItInjectsTheGlobalTimeoutDefaultsIntoTheDriverConfig(): void
    {
        $this->makeSut()->make('openAiCompatible', []);

        static::assertSame(60, $this->capturedConfig['request_timeout']);
        static::assertSame(120, $this->capturedConfig['stall_timeout']);
    }

    public function testItLetsProviderAdapterSettingsOverrideTheTimeouts(): void
    {
        $this->makeSut(['request_timeout' => 300])->make('openAiCompatible', []);

        static::assertSame(300, $this->capturedConfig['request_timeout']);
        static::assertSame(120, $this->capturedConfig['stall_timeout']);
    }

    public function testItLetsTheAdapterConfigOverrideTheTimeouts(): void
    {
        $this->makeSut()->make('openAiCompatible', ['request_timeout' => 0]);

        static::assertSame(0, $this->capturedConfig['request_timeout']);
    }
}
