<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\OpenAiLikeAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenAiLikeAdapter::class)]
class OpenAiLikeAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): OpenAiLikeAdapter
    {
        return new OpenAiLikeAdapter();
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

    private function makeAdapterWithClient(ModelListClient $client, string|null $baseUrl = null): OpenAiLikeAdapter
    {
        return new class($client, $baseUrl) extends OpenAiLikeAdapter {
            public function __construct(
                private readonly ModelListClient $injectedClient,
                string|null $baseUrl
            ) {
                $this->baseUrl = $baseUrl;
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
        static::assertInstanceOf(OpenAiLikeAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver — URL resolution
    // =========================================================================

    public function testItCreateDriverUsesProviderApiUrlWhenBaseUrlIsNull(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_url' => 'https://api.custom-provider.com/v1', 'api_key' => 'k']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($name, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('https://api.custom-provider.com/v1', $captured['url']);
    }

    public function testItCreateDriverPrefersHardCodedBaseUrlOverProviderApiUrl(): void
    {
        // Subclass with a fixed baseUrl
        $sut = new class extends OpenAiLikeAdapter {
            protected string|null $baseUrl = 'https://fixed.endpoint.com/v1';
        };

        $provider = new AiProvider(['api_url' => 'https://should-be-ignored.com', 'api_key' => 'k']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($name, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('https://fixed.endpoint.com/v1', $captured['url']);
    }

    public function testItCreateDriverPassesApiKey(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_url' => 'https://api.example.com', 'api_key' => 'my-api-key']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($name, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('my-api-key', $captured['key']);
    }

    public function testItCreateDriverUsesOpenAiDriverName(): void
    {
        $sut          = $this->makeAdapter();
        $provider     = new AiProvider(['api_url' => 'https://api.example.com', 'api_key' => 'k']);
        $capturedName = null;

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName) use (&$capturedName) {
                $capturedName = $driverName;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame(Lab::OpenAI, $capturedName);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsCollectionOfAiModels(): void
    {
        $client = $this->makeModelListClient([
            'data' => [
                ['id' => 'custom-model-a'],
                ['id' => 'custom-model-b'],
            ],
        ]);

        $provider = $this->makeProvider();

        $sut    = $this->makeAdapterWithClient($client);
        $result = $sut->getModels($provider);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
    }

    public function testItGetModelsMapsModelId(): void
    {
        $client = $this->makeModelListClient([
            'data' => [['id' => 'custom-model-x']],
        ]);

        $provider = $this->makeProvider();

        $sut    = $this->makeAdapterWithClient($client);
        $result = $sut->getModels($provider);

        static::assertSame('custom-model-x', $result->first()->model_id);
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
