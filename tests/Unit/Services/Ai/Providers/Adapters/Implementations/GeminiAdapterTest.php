<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\GeminiAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(GeminiAdapter::class)]
class GeminiAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeLocaleService(): LocaleService
    {
        $locale  = new Locale('en', 'en', 'English', 'EN');
        $service = $this->createMock(LocaleService::class);
        $service->method('getLocale')->willReturn($locale);
        return $service;
    }

    private function makeAdapter(): GeminiAdapter
    {
        return new GeminiAdapter($this->makeLocaleService());
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
     * Returns a subclass of GeminiAdapter where createModelListClient() is intercepted.
     */
    private function makeAdapterWithClient(ModelListClient $client): GeminiAdapter
    {
        $localeService = $this->makeLocaleService();
        return new class($localeService, $client) extends GeminiAdapter {
            public function __construct(
                LocaleService $localeService,
                private readonly ModelListClient $injectedClient
            ) {
                parent::__construct($localeService);
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
        static::assertInstanceOf(GeminiAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItCreateDriverPassesApiKeyToFactory(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'gm-test-key']);
        $captured = [];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$captured) {
                $captured = $config;
                return $this->createMock(Driver::class);
            });

        $sut->createDriver($provider, $factory);

        static::assertSame('gm-test-key', $captured['key']);
    }

    public function testItCreateDriverSuppliesBuilderClosure(): void
    {
        $sut      = $this->makeAdapter();
        $provider = new AiProvider(['api_key' => 'gm-test-key']);
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
    // getAdditionalDriverOptions
    // =========================================================================

    private function makeContext(int $maxTokens, int $maxThinkingTokens): AgentRequestContext
    {
        $params = AiModelParameters::fromArray([
            'max_tokens'          => $maxTokens,
            'max_thinking_tokens' => $maxThinkingTokens,
        ]);

        return new AgentRequestContext(
            provider:         $this->makeProvider(),
            model:            new AiModel(['model_id' => 'gemini-pro']),
            modelParameters:  $params,
        );
    }

    public function testItGetAdditionalDriverOptionsReturnsEmptyArrayForNonTextAgent(): void
    {
        $sut     = $this->makeAdapter();
        $agent   = $this->createMock(Agent::class);
        $context = $this->makeContext(4096, 2000);

        $result = $sut->getAdditionalDriverOptions($agent, $context);

        static::assertSame([], $result);
    }

    public function testItGetAdditionalDriverOptionsReturnsConfigForTextGeneratingAgent(): void
    {
        $sut     = $this->makeAdapter();
        $context = $this->makeContext(4096, 2000);
        $agent   = $this->createMock(AbstractTextGeneratingAgent::class);

        $result = $sut->getAdditionalDriverOptions($agent, $context);

        static::assertArrayHasKey('generationConfig', $result);
        static::assertArrayHasKey('safetySettings', $result);
    }

    public function testItGetAdditionalDriverOptionsCapsBudgetAtHalfMaxTokens(): void
    {
        $sut     = $this->makeAdapter();
        $context = $this->makeContext(4000, 8000);
        $agent   = $this->createMock(AbstractTextGeneratingAgent::class);

        $result = $sut->getAdditionalDriverOptions($agent, $context);

        // budget capped at 4000/2 = 2000, not the raw 8000
        static::assertSame(2000, $result['generationConfig']['thinkingConfig']['thinkingBudget']);
    }

    public function testItGetAdditionalDriverOptionsUsesRawBudgetWhenBelowHalfMax(): void
    {
        $sut     = $this->makeAdapter();
        $context = $this->makeContext(8000, 1000);
        $agent   = $this->createMock(AbstractTextGeneratingAgent::class);

        $result = $sut->getAdditionalDriverOptions($agent, $context);

        static::assertSame(1000, $result['generationConfig']['thinkingConfig']['thinkingBudget']);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsStripsModelsPrefix(): void
    {
        $client = $this->makeModelListClient([
            'models' => [
                ['name' => 'models/gemini-pro', 'description' => ''],
            ],
        ]);
        $sut      = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame('gemini-pro', $result->first()->model_id);
    }

    public function testItGetModelsKeepsIdWithoutModelsPrefix(): void
    {
        $client = $this->makeModelListClient([
            'models' => [
                ['name' => 'gemini-pro', 'description' => ''],
            ],
        ]);
        $sut      = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertSame('gemini-pro', $result->first()->model_id);
    }

    public function testItGetModelsAttachesReasoningFlagWhenThinkingIsTrue(): void
    {
        $client = $this->makeModelListClient([
            'models' => [
                ['name' => 'models/gemini-thinking', 'thinking' => true, 'description' => ''],
            ],
        ]);
        $sut      = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        /** @var AiModel $model */
        $model = $result->first();
        static::assertContains(WellKnownModelFlags::FEATURE_REASONING, $model->flags->toArray());
    }

    public function testItGetModelsDoesNotAttachReasoningFlagWhenThinkingIsAbsent(): void
    {
        $client = $this->makeModelListClient([
            'models' => [
                ['name' => 'models/gemini-pro', 'description' => ''],
            ],
        ]);
        $sut      = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        /** @var AiModel $model */
        $model = $result->first();
        $flags = $model->flags?->toArray() ?? [];
        static::assertNotContains(WellKnownModelFlags::FEATURE_REASONING, $flags);
    }

    public function testItGetModelsReturnsMappedCollection(): void
    {
        $client = $this->makeModelListClient([
            'models' => [
                ['name' => 'models/gemini-pro', 'description' => ''],
                ['name' => 'models/gemini-ultra', 'description' => ''],
            ],
        ]);
        $sut      = $this->makeAdapterWithClient($client);
        $provider = $this->makeProvider();

        $result = $sut->getModels($provider);

        static::assertInstanceOf(Collection::class, $result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(AiModel::class, $result);
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

    public function testItGetNativeToolFactoryReturnsWebFetchFactory(): void
    {
        $sut     = $this->makeAdapter();
        $factory = $sut->getNativeToolFactoryForCapability(WellKnownCapabilities::WEB_FETCH);

        static::assertIsCallable($factory);
        static::assertInstanceOf(WebFetch::class, $factory());
    }

    public function testItGetNativeToolFactoryReturnsNullForUnknownCapability(): void
    {
        $sut = $this->makeAdapter();
        static::assertNull($sut->getNativeToolFactoryForCapability('unknown_capability'));
    }
}
