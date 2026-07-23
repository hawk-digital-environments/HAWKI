<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\AnthropicAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AnthropicAdapter::class)]
class AnthropicAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): AnthropicAdapter
    {
        return new AnthropicAdapter();
    }

    private function makeProvider(int $id = 1): AiProviderProxy
    {
        $model     = new AiProvider();
        $model->id = $id;

        $driver = $this->createMock(Driver::class);
        $driver->method('providerCredentials')->willReturn(['key' => 'test-key']);

        return new AiProviderProxy(
            provider: $model,
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $driver
        );
    }

    /**
     * Builds a ModelListClient whose get() returns a ModelListResponse with the given payload.
     */
    private function makeModelListClient(array $payload, string $expectedRoute = '/models'): ModelListClient
    {
        $rawResponse = $this->createMock(Response::class);
        $rawResponse->method('json')->willReturn($payload);
        $rawResponse->method('successful')->willReturn(true);

        $response = new ModelListResponse($rawResponse);

        $client = $this->createMock(ModelListClient::class);
        $client->expects(static::once())
            ->method('get')
            ->with($expectedRoute)
            ->willReturn($response);

        return $client;
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeAdapter();
        static::assertInstanceOf(AnthropicAdapter::class, $sut);
    }

    public function testItCreateDriverPassesApiKeyToFactory(): void
    {
        $sut = $this->makeAdapter();

        $provider = new AiProvider(['api_key' => 'sk-ant-test-key']);

        $factory = $this->createMock(DriverFactory::class);
        $factory->expects(static::once())
            ->method('make')
            ->with(
                static::anything(),
                static::callback(fn(array $config) => $config['key'] === 'sk-ant-test-key')
            )
            ->willReturn($this->createMock(Driver::class));

        $sut->createDriver($provider, $factory);
    }

    public function testItCreateDriverReturnsDriverInstance(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'sk-ant-test-key']);
        $driver   = $this->createMock(Driver::class);

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')->willReturn($driver);

        $result = $sut->createDriver($provider, $factory);

        static::assertSame($driver, $result);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsCollectionOfAiModels(): void
    {
        $sut = new class extends AnthropicAdapter {
            public \Closure $clientFactory;

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return ($this->clientFactory)();
            }
        };

        $client = $this->makeModelListClient([
            'data' => [
                ['id' => 'claude-3-opus-20240229'],
                ['id' => 'claude-3-sonnet-20240229'],
            ],
        ]);

        $sut->clientFactory = fn() => $client;

        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
    }

    public function testItGetModelsMapsModelIdFromResponseData(): void
    {
        $sut = new class extends AnthropicAdapter {
            public \Closure $clientFactory;

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return ($this->clientFactory)();
            }
        };

        $client = $this->makeModelListClient([
            'data' => [['id' => 'claude-3-opus-20240229']],
        ]);

        $sut->clientFactory = fn() => $client;

        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame('claude-3-opus-20240229', $result->first()->model_id);
    }

    public function testItGetModelsReturnsEmptyCollectionWhenNoModels(): void
    {
        $sut = new class extends AnthropicAdapter {
            public \Closure $clientFactory;

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return ($this->clientFactory)();
            }
        };

        $client = $this->makeModelListClient(['data' => []]);
        $sut->clientFactory = fn() => $client;

        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertCount(0, $result);
    }

    // =========================================================================
    // Inherited defaults
    // =========================================================================

    public function testItGetNativeToolFactoryForCapabilityReturnsNull(): void
    {
        $sut = $this->makeAdapter();
        static::assertNull($sut->getNativeToolFactoryForCapability('web_search'));
    }

    public function testItGetNameLabelReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNameLabel());
    }
}
