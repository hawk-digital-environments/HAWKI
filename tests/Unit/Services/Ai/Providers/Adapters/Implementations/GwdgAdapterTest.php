<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\GwdgAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(GwdgAdapter::class)]
class GwdgAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

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

    private function makeProvider(
        string|null $apiUrl = null,
        string      $apiKey = 'gwdg-test-key'
    ): AiProviderProxy
    {
        $model = new AiProvider([
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
        ]);
        $model->id = 1;

        $driver = $this->createMock(Driver::class);
        $driver->method('providerCredentials')->willReturn(['key' => $apiKey]);

        return new AiProviderProxy(
            provider: $model,
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $driver
        );
    }

    /**
     * Builds real status and demand collections holding one model per given model id.
     *
     * @return array{AiModelOnlineStatusCollection, AiModelDemandCollection}
     */
    private function makeStatusCollections(string ...$modelIds): array
    {
        $models = new AiModelCollection(
            array_map(static fn(string $id) => new AiModel(['model_id' => $id]), $modelIds)
        );
        $logger = $this->createMock(LoggerInterface::class);

        return [
            new AiModelOnlineStatusCollection($models, $logger),
            new AiModelDemandCollection($models, $logger),
        ];
    }

    /**
     * Returns a GwdgAdapter subclass with the HTTP layer replaced by $client.
     */
    private function makeAdapterWithClient(ModelListClient $client): GwdgAdapter
    {
        return new class($client) extends GwdgAdapter {
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
        static::assertInstanceOf(GwdgAdapter::class, new GwdgAdapter());
    }

    // =========================================================================
    // supportsFileAsAttachment
    // =========================================================================

    public function testItSupportsImageAttachments(): void
    {
        $sut = new GwdgAdapter();
        $file = $this->createMock(FileInterface::class);
        $file->method('getMimeType')->willReturn('image/png');

        static::assertTrue($sut->supportsFileAsAttachment($file));
    }

    public static function provideTestItRejectsNonImageAttachmentsData(): iterable
    {
        yield 'pdf' => ['application/pdf'];
        yield 'word' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        yield 'text' => ['text/plain'];
        yield 'json' => ['application/json'];
    }

    #[DataProvider('provideTestItRejectsNonImageAttachmentsData')]
    public function testItRejectsNonImageAttachments(string $mimeType): void
    {
        $sut = new GwdgAdapter();
        $file = $this->createMock(FileInterface::class);
        $file->method('getMimeType')->willReturn($mimeType);

        static::assertFalse($sut->supportsFileAsAttachment($file));
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItCreateDriverUsesDefaultBaseUrl(): void
    {
        $sut = new GwdgAdapter();
        $provider = new AiProvider(['api_url' => null, 'api_key' => 'k']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($name, array $config, ?\Closure $builder) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('https://chat-ai.academiccloud.de/v1', $captured['url']);
    }

    public function testItCreateDriverUsesConfiguredUrlWhenProvided(): void
    {
        $sut = new GwdgAdapter();
        $provider = new AiProvider(['api_url' => 'https://custom.gwdg.example.com/v1', 'api_key' => 'k']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($name, array $config, ?\Closure $builder) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('https://custom.gwdg.example.com/v1', $captured['url']);
    }

    public function testItCreateDriverPassesApiKey(): void
    {
        $sut = new GwdgAdapter();
        $provider = new AiProvider(['api_url' => null, 'api_key' => 'my-gwdg-key']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($name, array $config, ?\Closure $builder) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('my-gwdg-key', $captured['key']);
    }

    // =========================================================================
    // getModels — model type classification
    // =========================================================================

    public function testItGetModelsSetsModelTypeToChatWhenBothIoContainText(): void
    {
        $client = $this->makeModelListClient([
            'data' => [[
                'id' => 'text-model',
                'input' => ['text'],
                'output' => ['text'],
                'name' => 'Text Model',
            ]],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame(WellKnownModelTypes::CHAT, $result->first()->model_type);
    }

    public function testItGetModelsDoesNotSetModelTypeWhenOutputLacksText(): void
    {
        $client = $this->makeModelListClient([
            'data' => [[
                'id' => 'image-model',
                'input' => ['text'],
                'output' => ['image'],
                'name' => 'Image Model',
            ]],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertNull($result->first()->model_type);
    }

    public function testItGetModelsSetsDocumentationUrl(): void
    {
        $client = $this->makeModelListClient([
            'data' => [[
                'id' => 'any-model',
                'input' => ['text'],
                'output' => ['text'],
                'name' => 'Any Model',
            ]],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame(GwdgAdapter::DEFAULT_DOCUMENTATION_URL, $result->first()->documentation_url);
    }

    public function testItGetModelsSetsDisplayLabel(): void
    {
        $client = $this->makeModelListClient([
            'data' => [[
                'id' => 'my-model',
                'input' => [],
                'output' => [],
                'name' => 'My Display Name',
            ]],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame('My Display Name', $result->first()->label);
    }

    public function testItGetModelsReturnsCollection(): void
    {
        $client = $this->makeModelListClient([
            'data' => [
                ['id' => 'model-a', 'input' => [], 'output' => [], 'name' => 'A'],
                ['id' => 'model-b', 'input' => [], 'output' => [], 'name' => 'B'],
            ],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
    }

    // =========================================================================
    // checkModelStatus — status mapping
    // =========================================================================

    public static function provideTestItCheckModelStatusMapsStatusData(): iterable
    {
        yield 'ready maps to online' => ['ready', OnlineStatus::ONLINE];
        yield 'offline maps to offline' => ['offline', OnlineStatus::OFFLINE];
        yield 'unknown maps to unknown' => ['unknown', OnlineStatus::UNKNOWN];
        yield 'other maps to unknown' => ['degraded', OnlineStatus::UNKNOWN];
    }

    #[DataProvider('provideTestItCheckModelStatusMapsStatusData')]
    public function testItCheckModelStatusMapsStatus(string $gwdgStatus, OnlineStatus $expectedStatus): void
    {
        $client = $this->makeModelListClient([
            'data' => [[
                'id' => 'model-x',
                'status' => $gwdgStatus,
                'demand' => 0,
                'input' => [],
                'output' => [],
                'name' => 'Model X',
            ]],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        [$statusCollection, $demandCollection] = $this->makeStatusCollections('model-x');

        $sut->checkModelStatus($statusCollection, $demandCollection, $provider);

        $statuses = iterator_to_array($statusCollection->getChangedList());
        static::assertSame($expectedStatus, $statuses['model-x']);
    }

    // =========================================================================
    // checkModelStatus — demand mapping
    // =========================================================================

    public static function provideTestItCheckModelStatusMapsDemandData(): iterable
    {
        yield 'demand 0 maps to low' => [0, ModelDemand::LOW];
        yield 'demand 1 maps to low' => [1, ModelDemand::LOW];
        yield 'demand 2 maps to medium' => [2, ModelDemand::MEDIUM];
        yield 'demand 3 maps to medium' => [3, ModelDemand::MEDIUM];
        yield 'demand 4 maps to high' => [4, ModelDemand::HIGH];
        yield 'demand 5 maps to high' => [5, ModelDemand::HIGH];
    }

    #[DataProvider('provideTestItCheckModelStatusMapsDemandData')]
    public function testItCheckModelStatusMapsDemand(int $demandInt, ModelDemand $expectedDemand): void
    {
        $client = $this->makeModelListClient([
            'data' => [[
                'id' => 'model-x',
                'status' => 'ready',
                'demand' => $demandInt,
                'input' => [],
                'output' => [],
                'name' => 'Model X',
            ]],
        ]);

        $sut = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        [$statusCollection, $demandCollection] = $this->makeStatusCollections('model-x');

        $sut->checkModelStatus($statusCollection, $demandCollection, $provider);

        $demands = iterator_to_array($demandCollection->getChangedList());
        static::assertSame($expectedDemand, $demands['model-x']);
    }
}
