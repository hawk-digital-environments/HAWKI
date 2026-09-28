<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\AnthropicAdapter;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AnthropicAdapter::class)]
class AnthropicAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(\Psr\Log\LoggerInterface|null $logger = null): AnthropicAdapter
    {
        return new AnthropicAdapter($logger ?? new \Psr\Log\NullLogger());
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

    private function makeRequestContext(
        bool $hasReasoning,
        bool $hasSamplingParameters = false,
        AiModelParameters|null $parameters = null,
    ): AgentRequestContext {
        $flagNames = $hasReasoning ? [WellKnownModelFlags::FEATURE_REASONING] : [];
        if ($hasSamplingParameters) {
            $flagNames[] = WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS;
        }

        $flags = AiModelFlags::fromArray($flagNames);
        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnCallback(
            fn(string $key) => $key === 'flags' ? $flags : null
        );

        return new AgentRequestContext(
            provider: $this->makeProvider(),
            model: $model,
            modelParameters: $parameters ?? new AiModelParameters(),
        );
    }

    private function makeTextAgent(AgentRequestContext $context): ChatAgent
    {
        return new ChatAgent(
            context: $context,
            instructions: 'Be helpful.',
            messages: [],
            tools: [],
            promptString: 'Hello AI',
        );
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
        $sut = new class(new \Psr\Log\NullLogger()) extends AnthropicAdapter {
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
        $sut = new class(new \Psr\Log\NullLogger()) extends AnthropicAdapter {
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
        $sut = new class(new \Psr\Log\NullLogger()) extends AnthropicAdapter {
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
    // getAdditionalDriverOptions
    // =========================================================================

    public function testItDoesNotEnableThinkingForNonReasoningModels(): void
    {
        $context = $this->makeRequestContext(hasReasoning: false);

        static::assertSame([], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItDoesNotEnableThinkingForNonTextAgents(): void
    {
        $context = $this->makeRequestContext(hasReasoning: true);

        static::assertSame([], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->createMock(Agent::class),
            $context,
        ));
    }

    public function testItEnablesThinkingWithoutSamplingParameters(): void
    {
        $context = $this->makeRequestContext(hasReasoning: true);

        static::assertSame([
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => 2_048,
            ],
        ], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItLimitsThinkingBudgetAndNeutralisesTemperature(): void
    {
        $parameters = (new AiModelParameters())
            ->setMaxTokens(8_192)
            ->setMaxThinkingTokens(6_000)
            ->setTemperature(0.7);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        static::assertSame([
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => 4_096,
            ],
            'temperature' => 1.0,
        ], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItWarnsWhenSamplingParametersAreNeutralised(): void
    {
        $warnings = [];
        $logger   = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });

        $parameters = (new AiModelParameters())
            ->setMaxTokens(8_192)
            ->setTemperature(0.3)
            ->setTopP(0.5);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        $this->makeAdapter($logger)->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        );

        static::assertCount(2, $warnings);
        static::assertStringContainsString('temperature', $warnings[0]);
        static::assertStringContainsString('top-p', $warnings[1]);
    }

    public function testItDisablesThinkingAndWarnsWhenRequestedBudgetIsBelowAnthropicsMinimum(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(static::stringContains("below Anthropic's minimum"));

        $parameters = (new AiModelParameters())
            ->setMaxTokens(8_192)
            ->setMaxThinkingTokens(512);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        static::assertSame([], $this->makeAdapter($logger)->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItDoesNotEnableThinkingWhenMaxTokensIsTooSmall(): void
    {
        $parameters = (new AiModelParameters())
            ->setMaxTokens(1_024)
            ->setMaxThinkingTokens(2_048);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        static::assertSame([], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItNeutralisesTopPBelowAnthropicsMinimum(): void
    {
        $parameters = (new AiModelParameters())
            ->setTopP(0.9);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        $result = $this->makeAdapter()->getAdditionalDriverOptions($this->makeTextAgent($context), $context);

        static::assertSame(1.0, $result['top_p']);
    }

    public function testItLeavesCompatibleTopPUnchanged(): void
    {
        $parameters = (new AiModelParameters())
            ->setTopP(0.97);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        $result = $this->makeAdapter()->getAdditionalDriverOptions($this->makeTextAgent($context), $context);

        static::assertArrayNotHasKey('top_p', $result);
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
