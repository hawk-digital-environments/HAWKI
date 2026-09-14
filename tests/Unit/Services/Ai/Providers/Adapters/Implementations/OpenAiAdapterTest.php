<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\OpenAiAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\WebSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenAiAdapter::class)]
class OpenAiAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): OpenAiAdapter
    {
        return new OpenAiAdapter();
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

    private function makeAdapterWithClient(ModelListClient $client): OpenAiAdapter
    {
        return new class($client) extends OpenAiAdapter {
            public function __construct(private readonly ModelListClient $injectedClient)
            {
            }

            protected function createModelListClient(\Illuminate\Http\Client\PendingRequest $request): ModelListClient
            {
                return $this->injectedClient;
            }
        };
    }

    private function makeRequestContext(bool $hasReasoning): AgentRequestContext
    {
        $flags = AiModelFlags::fromArray(
            $hasReasoning ? [WellKnownModelFlags::FEATURE_REASONING] : []
        );
        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnCallback(
            fn(string $key) => $key === 'flags' ? $flags : null
        );

        return new AgentRequestContext(
            provider: $this->makeProvider(),
            model: $model,
            modelParameters: new AiModelParameters(),
        );
    }

    private function makeTextAgent(AgentRequestContext $context, iterable $tools = []): ChatAgent
    {
        return new ChatAgent(
            context: $context,
            instructions: 'Be helpful.',
            messages: [],
            tools: $tools,
            promptString: 'Hello AI',
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(OpenAiAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItCreateDriverPassesApiKeyToFactory(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'sk-openai-test']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('sk-openai-test', $captured['key']);
    }

    public function testItCreateDriverUsesOpenAiDriverName(): void
    {
        $sut          = $this->makeAdapter();
        $provider     = new AiProvider(['api_key' => 'sk-openai-test']);
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

    public function testItCreateDriverSuppliesBuilderClosure(): void
    {
        $sut           = $this->makeAdapter();
        $provider      = new AiProvider(['api_key' => 'sk-openai-test']);
        $builderPassed = false;

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config, ?\Closure $builder) use (&$builderPassed) {
                $builderPassed = $builder !== null;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertTrue($builderPassed);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsCollectionOfAiModels(): void
    {
        $client = $this->makeModelListClient([
            'data' => [
                ['id' => 'gpt-4o'],
                ['id' => 'gpt-4-turbo'],
                ['id' => 'gpt-3.5-turbo'],
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
            'data' => [['id' => 'gpt-4o']],
        ]);

        $provider = $this->makeProvider();

        $sut    = $this->makeAdapterWithClient($client);
        $result = $sut->getModels($provider);

        static::assertSame('gpt-4o', $result->first()->model_id);
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
    // getAdditionalDriverOptions
    // =========================================================================

    public function testItGetAdditionalDriverOptionsAddsReasoningForReasoningModels(): void
    {
        $context = $this->makeRequestContext(hasReasoning: true);

        $result = $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        );

        static::assertSame(['reasoning' => ['summary' => 'auto']], $result);
    }

    public function testItGetAdditionalDriverOptionsAddsWebSearchSourcesWithoutReasoning(): void
    {
        $context = $this->makeRequestContext(hasReasoning: false);

        $result = $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context, [new WebSearch()]),
            $context,
        );

        static::assertSame(['include' => ['web_search_call.action.sources']], $result);
    }

    public function testItGetAdditionalDriverOptionsAddsReasoningAndWebSearchSources(): void
    {
        $context = $this->makeRequestContext(hasReasoning: true);

        $result = $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context, [new WebSearch()]),
            $context,
        );

        static::assertSame([
            'reasoning' => ['summary' => 'auto'],
            'include' => ['web_search_call.action.sources'],
        ], $result);
    }

    public function testItGetAdditionalDriverOptionsReturnsEmptyArrayWithoutReasoningOrWebSearch(): void
    {
        $context = $this->makeRequestContext(hasReasoning: false);

        $result = $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        );

        static::assertSame([], $result);
    }

    public function testItGetAdditionalDriverOptionsReturnsEmptyArrayForNonTextAgent(): void
    {
        $context = $this->makeRequestContext(hasReasoning: true);

        $result = $this->makeAdapter()->getAdditionalDriverOptions(
            $this->createMock(\Laravel\Ai\Contracts\Agent::class),
            $context,
        );

        static::assertSame([], $result);
    }

    public function testItGetAdditionalDriverOptionsAfterEmptyToolGeneratorWasConsumed(): void
    {
        $context = $this->makeRequestContext(hasReasoning: false);
        $tools = (static function (): \Generator {
            yield from [];
        })();
        $agent = $this->makeTextAgent($context, $tools);

        // The SDK collects tools before the gateway requests provider options.
        static::assertSame([], [...$agent->tools()]);
        static::assertSame([], $this->makeAdapter()->getAdditionalDriverOptions($agent, $context));
    }

    public function testItRetainsWebSearchOptionsAfterToolGeneratorWasConsumed(): void
    {
        $context = $this->makeRequestContext(hasReasoning: true);
        $webSearch = new WebSearch();
        $tools = (static function () use ($webSearch): \Generator {
            yield $webSearch;
        })();
        $agent = $this->makeTextAgent($context, $tools);

        static::assertSame([$webSearch], [...$agent->tools()]);
        static::assertSame([
            'reasoning' => ['summary' => 'auto'],
            'include' => ['web_search_call.action.sources'],
        ], $this->makeAdapter()->getAdditionalDriverOptions($agent, $context));
        static::assertSame([$webSearch], [...$agent->tools()]);
    }

    // =========================================================================
    // getNativeToolFactoryForCapability
    // =========================================================================

    public function testItGetNativeToolFactoryReturnsWebSearchFactory(): void
    {
        $sut     = $this->makeAdapter();
        $factory = $sut->getNativeToolFactoryForCapability(WellKnownCapabilities::WEB_SEARCH);

        static::assertIsCallable($factory);
        static::assertInstanceOf(WebSearch::class, $factory());
    }

    public function testItGetNativeToolFactoryReturnsNullForUnknownCapability(): void
    {
        $sut = $this->makeAdapter();
        static::assertNull($sut->getNativeToolFactoryForCapability('unknown'));
    }

    public function testItGetNativeToolFactoryReturnsNullForWebFetch(): void
    {
        // OpenAI does not natively support web_fetch (only Gemini does)
        $sut = $this->makeAdapter();
        static::assertNull($sut->getNativeToolFactoryForCapability(WellKnownCapabilities::WEB_FETCH));
    }
}
