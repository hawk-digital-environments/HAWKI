<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\AwsBedrockAdapter;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Providers\Values\ProviderSettings;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(AwsBedrockAdapter::class)]
class AwsBedrockAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(\Psr\Log\LoggerInterface|null $logger = null): AwsBedrockAdapter
    {
        return new AwsBedrockAdapter($logger ?? new \Psr\Log\NullLogger());
    }

    private function makeProvider(string $apiKey, array $adapterSettings = []): AiProvider
    {
        $settings = ProviderSettings::fromArray(['adapter' => $adapterSettings]);

        $provider = $this->createMock(AiProvider::class);
        $provider->method('__get')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'api_key'  => $apiKey,
                'settings' => $settings,
                default    => null,
            });

        return $provider;
    }

    private function makeFactory(array &$capturedConfig = []): DriverFactory
    {
        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$capturedConfig) {
                $capturedConfig = $config;
                return $this->createMock(Driver::class);
            });
        return $factory;
    }

    private function makeProviderProxy(int $id = 1): AiProviderProxy
    {
        $provider     = new AiProvider();
        $provider->id = $id;

        return new AiProviderProxy(
            provider: $provider,
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Driver::class),
        );
    }

    private function makeRequestContext(
        bool $hasReasoning,
        string $modelId = 'anthropic.claude-sonnet-4-5-20250929-v1:0',
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
            fn(string $key) => match ($key) {
                'flags' => $flags,
                'model_id' => $modelId,
                default => null,
            }
        );

        return new AgentRequestContext(
            provider: $this->makeProviderProxy(),
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
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeAdapter();
        static::assertInstanceOf(AwsBedrockAdapter::class, $sut);
    }

    // =========================================================================
    // createDriver — static IAM credentials
    // =========================================================================

    public function testItCreateDriverParsesStaticCredentials(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('AKIAIOSFODNN7EXAMPLE wJalrXUtnFEMI/K7MDENG');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('AKIAIOSFODNN7EXAMPLE', $captured['access_key_id']);
        static::assertSame('wJalrXUtnFEMI/K7MDENG', $captured['secret_access_key']);
        static::assertNull($captured['key']);
    }

    public function testItCreateDriverSetsNullKeyForStaticCredentials(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('MYACCESSKEY MYSECRETKEY');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertNull($captured['key']);
    }

    // =========================================================================
    // createDriver — bearer token
    // =========================================================================

    public function testItCreateDriverParsesTokenCredential(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('token:myBearerToken123');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('myBearerToken123', $captured['key']);
        static::assertNull($captured['access_key_id']);
        static::assertNull($captured['secret_access_key']);
    }

    // =========================================================================
    // createDriver — default region / version
    // =========================================================================

    public function testItCreateDriverUsesDefaultRegionWhenNotInAdapterSettings(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('KEY SECRET');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('eu-central-1', $captured['region']);
    }

    public function testItCreateDriverUsesDefaultVersionWhenNotInAdapterSettings(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('KEY SECRET');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('latest', $captured['version']);
    }

    public function testItCreateDriverUsesAdapterSettingsRegionWhenProvided(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('KEY SECRET', ['region' => 'us-west-2']);
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('us-west-2', $captured['region']);
    }

    // =========================================================================
    // createDriver — invalid key format throws
    // =========================================================================

    public static function provideTestItCreateDriverThrowsForInvalidKeyFormatData(): iterable
    {
        yield 'empty string'            => [''];
        yield 'plain key no space'      => ['JUSTAKEYNOSPACEORANYTHING'];
        yield 'token prefix but spaces' => ['token:FOO BAR'];
        yield 'three space-separated'   => ['A B C'];
    }

    #[DataProvider('provideTestItCreateDriverThrowsForInvalidKeyFormatData')]
    public function testItCreateDriverThrowsForInvalidKeyFormat(string $apiKey): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider($apiKey);
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(static::never())->method('make');

        $this->expectException(InvalidProviderConfigurationException::class);
        $this->expectExceptionMessage('Invalid API key format for AWS Bedrock provider');

        $sut->createDriver($provider, $factory);
    }

    // =========================================================================
    // Inherited defaults
    // =========================================================================

    public function testItGetNameLabelReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNameLabel());
    }

    public function testItGetNativeToolFactoryForCapabilityReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNativeToolFactoryForCapability('web_search'));
    }

    // =========================================================================
    // getAdditionalDriverOptions
    // =========================================================================

    public function testItDoesNotEnableThinkingForNonReasoningClaudeModels(): void
    {
        $context = $this->makeRequestContext(hasReasoning: false);

        static::assertSame([], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItDoesNotEnableThinkingForNonAnthropicModels(): void
    {
        $context = $this->makeRequestContext(
            hasReasoning: true,
            modelId: 'amazon.nova-pro-v1:0',
        );

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
            'additionalModelRequestFields' => [
                'thinking' => [
                    'type' => 'enabled',
                    'budget_tokens' => 2_048,
                ],
            ],
        ], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
    }

    public function testItLimitsThinkingBudgetAndNeutralisesSamplingParameters(): void
    {
        $parameters = (new AiModelParameters())
            ->setMaxTokens(8_192)
            ->setMaxThinkingTokens(6_000)
            ->setTemperature(0.5)
            ->setTopP(0.9);
        $context = $this->makeRequestContext(
            hasReasoning: true,
            hasSamplingParameters: true,
            parameters: $parameters,
        );

        static::assertSame([
            'additionalModelRequestFields' => [
                'thinking' => [
                    'type' => 'enabled',
                    'budget_tokens' => 4_096,
                ],
            ],
            'inferenceConfig' => [
                'maxTokens' => 8_192,
                'temperature' => 1.0,
            ],
        ], $this->makeAdapter()->getAdditionalDriverOptions(
            $this->makeTextAgent($context),
            $context,
        ));
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
}
