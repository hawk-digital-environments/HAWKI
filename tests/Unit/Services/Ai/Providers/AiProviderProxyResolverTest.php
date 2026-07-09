<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\ProviderNotFoundException;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\DriverFactoryFactory;
use App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AiProviderProxyResolver::class)]
class AiProviderProxyResolverTest extends TestCase
{
    private AiProviderRepository&MockObject $providerRepository;
    private ProviderAdapterRegistry&MockObject $adapterRegistry;
    private DriverFactoryFactory&MockObject $driverFactoryFactory;
    private AiProviderProxyResolver $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerRepository = $this->createMock(AiProviderRepository::class);
        $this->adapterRegistry = $this->createMock(ProviderAdapterRegistry::class);
        $this->driverFactoryFactory = $this->createMock(DriverFactoryFactory::class);

        $this->sut = new AiProviderProxyResolver(
            $this->providerRepository,
            $this->adapterRegistry,
            $this->driverFactoryFactory,
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(string $providerId = 'openAi'): AiProvider
    {
        $provider = new AiProvider();
        $provider->provider_id = $providerId;
        $provider->adapter_key = 'openai';
        return $provider;
    }

    private function wireAdapter(AiProvider $provider): ProviderAdapterInterface&MockObject
    {
        $driver = $this->createMock(Driver::class);
        $driverFactory = $this->createMock(DriverFactory::class);
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('createDriver')->willReturn($driver);

        $this->adapterRegistry->method('getForProvider')->with($provider)->willReturn($adapter);
        $this->driverFactoryFactory->method('createFactoryForProvider')->with($provider)->willReturn($driverFactory);

        return $adapter;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(AiProviderProxyResolver::class, $this->sut);
    }

    // =========================================================================
    // resolve — AiProvider instance passed directly
    // =========================================================================

    public function testItResolveReturnsAiProviderProxyWhenProviderInstanceGiven(): void
    {
        $provider = $this->makeProvider();
        $this->wireAdapter($provider);

        $result = $this->sut->resolve($provider);

        static::assertInstanceOf(AiProviderProxy::class, $result);
    }

    public function testItResolveSkipsDatabaseLookupWhenProviderInstanceGiven(): void
    {
        $provider = $this->makeProvider();
        $this->wireAdapter($provider);

        $this->providerRepository->expects(static::never())->method('findOneByProviderId');
        $this->providerRepository->expects(static::never())->method('findOne');

        $this->sut->resolve($provider);
    }

    public function testItResolveWrapsProviderInstanceInProxy(): void
    {
        $provider = $this->makeProvider();
        $this->wireAdapter($provider);

        $proxy = $this->sut->resolve($provider);

        static::assertSame($provider, $proxy->getRealProvider());
    }

    // =========================================================================
    // resolve — string provider_id
    // =========================================================================

    public function testItResolveFindsProviderByStringId(): void
    {
        $provider = $this->makeProvider('openAi');
        $this->providerRepository->method('findOneByProviderId')->with('openAi')->willReturn($provider);
        $this->wireAdapter($provider);

        $proxy = $this->sut->resolve('openAi');

        static::assertSame($provider, $proxy->getRealProvider());
    }

    public function testItResolveThrowsProviderNotFoundExceptionForUnknownStringId(): void
    {
        $this->providerRepository->method('findOneByProviderId')->willReturn(null);

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Could not find AI provider with identifier "%s".', 'unknown'));

        $this->sut->resolve('unknown');
    }

    // =========================================================================
    // resolve — integer primary key
    // =========================================================================

    public function testItResolveFindsProviderByIntegerId(): void
    {
        $provider = $this->makeProvider();
        $this->providerRepository->method('findOne')->with(42)->willReturn($provider);
        $this->wireAdapter($provider);

        $proxy = $this->sut->resolve(42);

        static::assertSame($provider, $proxy->getRealProvider());
    }

    public function testItResolveThrowsProviderNotFoundExceptionForUnknownIntegerId(): void
    {
        $this->providerRepository->method('findOne')->willReturn(null);

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Could not find AI provider with identifier "%s".', 99));

        $this->sut->resolve(99);
    }

    // =========================================================================
    // resolve — proxy contents
    // =========================================================================

    public function testItResolvePopulatesAdapterOnProxy(): void
    {
        $provider = $this->makeProvider();
        $adapter = $this->wireAdapter($provider);

        $proxy = $this->sut->resolve($provider);

        static::assertSame($adapter, $proxy->adapter);
    }

    public function testItResolvePopulatesDriverOnProxy(): void
    {
        $provider = $this->makeProvider();
        $driver = $this->createMock(Driver::class);
        $driverFactory = $this->createMock(DriverFactory::class);
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('createDriver')->willReturn($driver);

        $this->adapterRegistry->method('getForProvider')->willReturn($adapter);
        $this->driverFactoryFactory->method('createFactoryForProvider')->willReturn($driverFactory);

        $proxy = $this->sut->resolve($provider);

        static::assertSame($driver, $proxy->driver);
    }

    // =========================================================================
    // resolveForModel
    // =========================================================================

    public function testItResolveForModelResolvesViaProviderRelation(): void
    {
        $provider = $this->makeProvider();
        $model = new AiModel();
        $model->setRelation('provider', $provider);
        $this->wireAdapter($provider);

        $proxy = $this->sut->resolveForModel($model);

        static::assertSame($provider, $proxy->getRealProvider());
    }
}
