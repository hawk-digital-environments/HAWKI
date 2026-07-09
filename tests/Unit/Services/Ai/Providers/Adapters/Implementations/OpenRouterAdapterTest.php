<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\OpenRouterAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenRouterAdapter::class)]
class OpenRouterAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): OpenRouterAdapter
    {
        return new OpenRouterAdapter();
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

    private function makeModelListClient(array $payload): ModelListClient
    {
        $rawResponse = $this->createMock(Response::class);
        $rawResponse->method('json')->willReturn($payload);
        $rawResponse->method('successful')->willReturn(true);

        $response = new ModelListResponse($rawResponse);

        $client = $this->createMock(ModelListClient::class);
        $client->method('get')->willReturn($response);

        return $client;
    }

    private function makeAdapterWithClient(ModelListClient $client): OpenRouterAdapter
    {
        return new class($client) extends OpenRouterAdapter {
            public function __construct(private readonly ModelListClient $injectedClient)
            {
            }

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return $this->injectedClient;
            }
        };
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(OpenRouterAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItCreateDriverPassesApiKeyToFactory(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'sk-or-test-key']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->expects(static::once())
            ->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('sk-or-test-key', $captured['key']);
    }

    public function testItCreateDriverUsesOpenRouterDriverName(): void
    {
        $sut          = $this->makeAdapter();
        $provider     = new AiProvider(['api_key' => 'sk-or-test-key']);
        $capturedName = null;

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName) use (&$capturedName) {
                $capturedName = $driverName;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame(Lab::OpenRouter, $capturedName);
    }

    public function testItCreateDriverDoesNotPassUrl(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'sk-or-test-key']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        // OpenRouter uses a fixed endpoint baked into the driver; no url override needed
        static::assertArrayNotHasKey('url', $captured);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsCollectionOfAiModels(): void
    {
        $client = $this->makeModelListClient([
            'data' => [
                ['id' => 'openai/gpt-4o'],
                ['id' => 'anthropic/claude-3-opus'],
                ['id' => 'meta-llama/llama-3-8b-instruct'],
            ],
        ]);

        $provider = $this->makeProvider();

        $sut    = $this->makeAdapterWithClient($client);
        $result = $sut->getModels($provider);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(3, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
    }

    public function testItGetModelsMapsModelId(): void
    {
        $client = $this->makeModelListClient([
            'data' => [['id' => 'openai/gpt-4o']],
        ]);

        $provider = $this->makeProvider();

        $sut    = $this->makeAdapterWithClient($client);
        $result = $sut->getModels($provider);

        static::assertSame('openai/gpt-4o', $result->first()->model_id);
    }

    public function testItGetModelsReturnsEmptyCollectionWhenNoModels(): void
    {
        $client = $this->makeModelListClient(['data' => []]);

        $provider = $this->makeProvider();

        $sut    = $this->makeAdapterWithClient($client);
        $result = $sut->getModels($provider);

        static::assertCount(0, $result);
    }

    // =========================================================================
    // Inherited defaults
    // =========================================================================

    public function testItGetNativeToolFactoryForCapabilityReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNativeToolFactoryForCapability('web_search'));
    }

    public function testItGetNameLabelReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNameLabel());
    }
}
