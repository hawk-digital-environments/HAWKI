<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\DeepseekAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DeepseekAdapter::class)]
class DeepseekAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): DeepseekAdapter
    {
        return new DeepseekAdapter();
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
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(DeepseekAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItCreateDriverPassesApiKeyToFactory(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'ds-test-key']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->expects(static::once())
            ->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('ds-test-key', $captured['key']);
    }

    public function testItCreateDriverUsesDeepSeekDriverName(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'ds-test-key']);
        $capturedName = null;

        $factory = $this->createMock(DriverFactory::class);
        $factory->expects(static::once())
            ->method('make')
            ->willReturnCallback(function ($driverName) use (&$capturedName) {
                $capturedName = $driverName;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        // DeepSeek uses the string value of Lab::DeepSeek (not the enum itself)
        static::assertSame(Lab::DeepSeek->value, $capturedName);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsCollectionOfAiModels(): void
    {
        $sut = new class extends DeepseekAdapter {
            public \Closure $clientFactory;

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return ($this->clientFactory)();
            }
        };

        $client = $this->makeModelListClient([
            'data' => [
                ['id' => 'deepseek-chat'],
                ['id' => 'deepseek-coder'],
            ],
        ]);
        $sut->clientFactory = fn() => $client;

        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
    }

    public function testItGetModelsMapsModelIdCorrectly(): void
    {
        $sut = new class extends DeepseekAdapter {
            public \Closure $clientFactory;

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return ($this->clientFactory)();
            }
        };

        $client = $this->makeModelListClient([
            'data' => [['id' => 'deepseek-chat']],
        ]);
        $sut->clientFactory = fn() => $client;

        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame('deepseek-chat', $result->first()->model_id);
    }

    public function testItGetModelsReturnsEmptyCollectionWhenNoModels(): void
    {
        $sut = new class extends DeepseekAdapter {
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
        static::assertNull($this->makeAdapter()->getNativeToolFactoryForCapability('web_search'));
    }
}
