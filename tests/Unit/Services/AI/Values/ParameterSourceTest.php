<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Values;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Values\Exceptions\ProviderHasNoModelsException;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ParameterSource;
use App\Services\Ai\Values\ProviderSettings;
use App\Services\Ai\Values\WellKnownProviderSettings;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ParameterSource::class)]
class ParameterSourceTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray([]),
        );
        static::assertInstanceOf(ParameterSource::class, $sut);
    }

    // =========================================================================
    // getModel
    // =========================================================================

    public function testItReturnsTheModel(): void
    {
        $model = $this->makeModel();
        $sut = new ParameterSource($model, $this->makeProvider(), ModelParameters::fromArray([]));
        static::assertSame($model, $sut->getModel());
    }

    // =========================================================================
    // getProvider
    // =========================================================================

    public function testItReturnsTheProvider(): void
    {
        $provider = $this->makeProvider();
        $sut = new ParameterSource($this->makeModel(), $provider, ModelParameters::fromArray([]));
        static::assertSame($provider, $sut->getProvider());
    }

    // =========================================================================
    // getUsageType
    // =========================================================================

    public function testItReturnsTheUsageType(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray([]),
            'my_usage_type',
        );
        static::assertSame('my_usage_type', $sut->getUsageType());
    }

    public function testItDefaultsUsageTypeToMainApp(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray([]),
        );
        static::assertSame(WellKnownUsageTypes::MAIN_APP, $sut->getUsageType());
    }

    // =========================================================================
    // fromModel – three-level parameter merge
    // =========================================================================

    public function testItCreatesFromModel(): void
    {
        $sut = ParameterSource::fromModel($this->makeModel(provider: $this->makeProvider()), null);
        static::assertInstanceOf(ParameterSource::class, $sut);
    }

    public function testItStoresModelProviderViaFromModel(): void
    {
        $provider = $this->makeProvider();
        $model = $this->makeModel(provider: $provider);
        $sut = ParameterSource::fromModel($model, null);
        static::assertSame($model, $sut->getModel());
        static::assertSame($provider, $sut->getProvider());
    }

    public function testItAppliesProviderParametersAsBaseLayer(): void
    {
        $provider = $this->makeProvider(ModelParameters::fromArray(['temperature' => 0.3]));
        $sut = ParameterSource::fromModel($this->makeModel(provider: $provider), null);
        static::assertSame(0.3, $sut->getTemperature());
    }

    public function testItAppliesModelParametersOverProviderDefaults(): void
    {
        $provider = $this->makeProvider(ModelParameters::fromArray(['temperature' => 0.3]));
        $model = $this->makeModel(ModelParameters::fromArray(['temperature' => 0.7]), $provider);
        $sut = ParameterSource::fromModel($model, null);
        static::assertSame(0.7, $sut->getTemperature());
    }

    public function testItAppliesRequestParametersOverModelAndProviderDefaults(): void
    {
        $provider = $this->makeProvider(ModelParameters::fromArray(['temperature' => 0.3]));
        $model = $this->makeModel(ModelParameters::fromArray(['temperature' => 0.7]), $provider);
        $sut = ParameterSource::fromModel($model, ModelParameters::fromArray(['temperature' => 0.9]));
        static::assertSame(0.9, $sut->getTemperature());
    }

    public function testItFallsBackToBuiltInDefaultWhenNoLayerSetsParam(): void
    {
        $sut = ParameterSource::fromModel($this->makeModel(provider: $this->makeProvider()), null);
        static::assertSame(0.95, $sut->getTemperature());
    }

    public function testItMergesDisjointExtraParamsAcrossAllLayers(): void
    {
        $provider = $this->makeProvider(ModelParameters::fromArray(['stream' => true]));
        $model = $this->makeModel(ModelParameters::fromArray(['debug' => false]), $provider);
        $sut = ParameterSource::fromModel($model, ModelParameters::fromArray(['trace' => 'yes']));
        static::assertSame(
            ['stream' => true, 'debug' => false, 'trace' => 'yes'],
            $sut->toAdditionalArray()
        );
    }

    public function testItGivesRequestLayerHighestPriorityInExtraParams(): void
    {
        $provider = $this->makeProvider(ModelParameters::fromArray(['shared' => 'provider']));
        $model = $this->makeModel(ModelParameters::fromArray(['shared' => 'model']), $provider);
        $sut = ParameterSource::fromModel($model, ModelParameters::fromArray(['shared' => 'request']));
        static::assertSame('request', $sut->get('shared'));
    }

    public function testItDefaultsUsageTypeToMainAppViaFromModel(): void
    {
        $sut = ParameterSource::fromModel($this->makeModel(provider: $this->makeProvider()), null);
        static::assertSame(WellKnownUsageTypes::MAIN_APP, $sut->getUsageType());
    }

    public function testItAcceptsCustomUsageTypeViaFromModel(): void
    {
        $sut = ParameterSource::fromModel($this->makeModel(provider: $this->makeProvider()), null, 'custom_type');
        static::assertSame('custom_type', $sut->getUsageType());
    }

    // =========================================================================
    // fromProvider
    // =========================================================================

    public function testItCreatesFromProviderUsingFirstModel(): void
    {
        $provider = $this->makeProvider();
        $model = $this->makeModel(provider: $provider);
        $sut = ParameterSource::fromProvider($this->makeProviderWithModels($model), null);
        static::assertSame($model, $sut->getModel());
    }

    public function testItThrowsWhenProviderHasNoModels(): void
    {
        $this->expectException(ProviderHasNoModelsException::class);
        $this->expectExceptionMessage(sprintf(
            'Provider "%s" has no models, cannot create ParameterSource.',
            'My Provider'
        ));
        ParameterSource::fromProvider($this->makeProviderWithModels(null, 'My Provider'), null);
    }

    // =========================================================================
    // __call proxy
    // =========================================================================

    public function testItProxiesGenericGetToMergedParameters(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray(['my_key' => 'my_value']),
        );
        static::assertSame('my_value', $sut->get('my_key'));
    }

    public function testItProxiesHasToMergedParameters(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray(['exists' => true]),
        );
        static::assertTrue($sut->has('exists'));
        static::assertFalse($sut->has('missing'));
    }

    public function testItProxiesToAdditionalArrayToMergedParameters(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray(['temperature' => 0.5, 'extra' => 'kept']),
        );
        static::assertSame(['extra' => 'kept'], $sut->toAdditionalArray());
    }

    public function testItThrowsBadMethodCallExceptionForUnknownMethod(): void
    {
        $sut = new ParameterSource(
            $this->makeModel(),
            $this->makeProvider(),
            ModelParameters::fromArray([]),
        );
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method nonExistentMethod does not exist on ' . ParameterSource::class);
        $sut->nonExistentMethod();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(ModelParameters|null $parameters = null): AiProvider&MockObject
    {
        $provider = $this->createMock(AiProvider::class);
        $settings = ProviderSettings::fromArray(
            $parameters ? [WellKnownProviderSettings::MODEL_PARAMETERS => $parameters->toArray()] : []
        );
        $provider->method('__get')->willReturnMap([['settings', $settings]]);
        return $provider;
    }

    private function makeModel(ModelParameters|null $parameters = null, AiProvider|null $provider = null): AiModel&MockObject
    {
        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnMap([
            ['parameters', $parameters ?? ModelParameters::fromArray([])],
            ['provider', $provider ?? $this->makeProvider()],
        ]);
        return $model;
    }

    /**
     * Builds an AiProvider mock that exposes a `models` collection and a `name` attribute,
     * as required by {@see ParameterSource::fromProvider()}.
     */
    private function makeProviderWithModels(AiModel|null $firstModel = null, string $name = 'Test Provider'): AiProvider&MockObject
    {
        $provider = $this->createMock(AiProvider::class);
        $collection = new Collection($firstModel ? [$firstModel] : []);
        $provider->method('__get')->willReturnMap([
            ['models', $collection],
            ['name', $name],
        ]);
        return $provider;
    }
}
