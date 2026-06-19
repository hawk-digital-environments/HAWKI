<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Values;

use App\Services\Ai\Exceptions\InvalidProviderSettingsOperationException;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ProviderSettings;
use App\Services\Ai\Values\WellKnownProviderSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProviderSettings::class)]
class AiProviderSettingsTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertInstanceOf(ProviderSettings::class, $sut);
    }

    // =========================================================================
    // getModelParameters
    // =========================================================================

    public function testItGetsModelParametersAsModelParametersInstance(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertInstanceOf(ModelParameters::class, $sut->getModelParameters());
    }

    public function testItGetsModelParametersWithStoredValues(): void
    {
        $sut = ProviderSettings::fromArray([
            'model_parameters' => ['temperature' => 0.7, 'max_tokens' => 2048],
        ]);
        static::assertSame(0.7, $sut->getModelParameters()->getTemperature());
        static::assertSame(2048, $sut->getModelParameters()->getMaxTokens());
    }

    public function testItGetsModelParametersWhenAbsentInInput(): void
    {
        // model_parameters is always initialised, even when missing from the input array
        $sut = ProviderSettings::fromArray([]);
        static::assertInstanceOf(ModelParameters::class, $sut->getModelParameters());
    }

    // =========================================================================
    // getAdapterSettings
    // =========================================================================

    public function testItGetsAdapterSettings(): void
    {
        $sut = ProviderSettings::fromArray([
            'adapter' => ['region' => 'eu-west-1', 'version' => '2024-01'],
        ]);
        static::assertSame(['region' => 'eu-west-1', 'version' => '2024-01'], $sut->getAdapterSettings());
    }

    public function testItGetsAdapterSettingsReturnsEmptyArrayWhenAbsent(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertSame([], $sut->getAdapterSettings());
    }

    // =========================================================================
    // has
    // =========================================================================

    public function testItReportsHasForPresentScalarKey(): void
    {
        $sut = ProviderSettings::fromArray(['custom' => 'value']);
        static::assertTrue($sut->has('custom'));
    }

    public function testItReportsHasForPresentInstanceKey(): void
    {
        $sut = ProviderSettings::fromArray([
            WellKnownProviderSettings::MODEL_PARAMETERS => ['temperature' => 0.5],
        ]);
        static::assertTrue($sut->has(WellKnownProviderSettings::MODEL_PARAMETERS));
    }

    public function testItReportsHasForAbsentKey(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertFalse($sut->has('nonexistent'));
    }

    // =========================================================================
    // get
    // =========================================================================

    public function testItGetsValueForPresentKey(): void
    {
        $sut = ProviderSettings::fromArray(['custom' => 'hello']);
        static::assertSame('hello', $sut->get('custom'));
    }

    public function testItGetsDefaultForAbsentKey(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertSame('fallback', $sut->get('missing', 'fallback'));
    }

    public function testItGetsNullForAbsentKeyWithNoDefault(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertNull($sut->get('missing'));
    }

    // =========================================================================
    // set
    // =========================================================================

    public function testItSetsScalarValue(): void
    {
        $sut = ProviderSettings::fromArray([]);
        $sut->set('new_key', 'new_value');
        static::assertSame('new_value', $sut->get('new_key'));
    }

    public function testItSetReturnsSelf(): void
    {
        $sut = ProviderSettings::fromArray([]);
        static::assertSame($sut, $sut->set('key', 'value'));
    }

    public function testItSetAcceptsCorrectTypeForInstanceKey(): void
    {
        $sut = ProviderSettings::fromArray([]);
        $params = ModelParameters::fromArray(['temperature' => 0.3]);
        $sut->set(WellKnownProviderSettings::MODEL_PARAMETERS, $params);
        static::assertSame($params, $sut->getModelParameters());
    }

    public function testItSetThrowsForInvalidTypeOnInstanceKey(): void
    {
        $sut = ProviderSettings::fromArray([]);
        $this->expectException(InvalidProviderSettingsOperationException::class);
        $this->expectExceptionMessage('Value for model_parameters must be an instance of ' . ModelParameters::class);
        $sut->set(WellKnownProviderSettings::MODEL_PARAMETERS, 'not-a-ModelParameters-object');
    }

    // =========================================================================
    // remove
    // =========================================================================

    public function testItRemovesScalarKey(): void
    {
        $sut = ProviderSettings::fromArray(['key' => 'value']);
        $sut->remove('key');
        static::assertFalse($sut->has('key'));
    }

    public function testItRemoveReturnsSelf(): void
    {
        $sut = ProviderSettings::fromArray(['key' => 'value']);
        static::assertSame($sut, $sut->remove('key'));
    }

    public function testItRemoveIsNoOpForAbsentKey(): void
    {
        $sut = ProviderSettings::fromArray([]);
        $sut->remove('nonexistent');
        static::assertFalse($sut->has('nonexistent'));
    }

    public function testItRemoveThrowsForInstanceKey(): void
    {
        $sut = ProviderSettings::fromArray([]);
        $this->expectException(InvalidProviderSettingsOperationException::class);
        $this->expectExceptionMessage('Cannot remove model_parameters as it is a required instance key.');
        $sut->remove(WellKnownProviderSettings::MODEL_PARAMETERS);
    }

    // =========================================================================
    // fromArray
    // =========================================================================

    public function testItFromArrayHydratesModelParameters(): void
    {
        $sut = ProviderSettings::fromArray([
            'model_parameters' => ['temperature' => 0.6],
        ]);
        static::assertInstanceOf(ModelParameters::class, $sut->getModelParameters());
        static::assertSame(0.6, $sut->getModelParameters()->getTemperature());
    }

    public function testItFromArrayInitialisesModelParametersWhenAbsent(): void
    {
        $sut = ProviderSettings::fromArray([]);
        // ModelParameters defaults kick in when the key is absent
        static::assertSame(0.95, $sut->getModelParameters()->getTemperature());
    }

    public function testItFromArraySilentlyFixesBrokenModelParametersData(): void
    {
        // non-array stored under model_parameters should not hard-fail
        $sut = ProviderSettings::fromArray([
            'model_parameters' => 'not-an-array',
        ]);
        static::assertInstanceOf(ModelParameters::class, $sut->getModelParameters());
    }

    public function testItFromArrayPreservesAdditionalScalarKeys(): void
    {
        $sut = ProviderSettings::fromArray(['custom' => 'preserved']);
        static::assertSame('preserved', $sut->get('custom'));
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItConvertsToArrayWithScalarKeys(): void
    {
        $sut = ProviderSettings::fromArray([
            'adapter' => ['region' => 'us-east-1'],
            'custom' => 'value',
        ]);
        $result = $sut->toArray();
        static::assertSame(['region' => 'us-east-1'], $result['adapter']);
        static::assertSame('value', $result['custom']);
    }

    public function testItToArrayOmitsEmptyModelParameters(): void
    {
        // When model_parameters serialises to [], it must be omitted to keep JSON compact
        $sut = ProviderSettings::fromArray([]);
        $result = $sut->toArray();
        static::assertArrayNotHasKey('model_parameters', $result);
    }

    public function testItToArrayIncludesNonEmptyModelParameters(): void
    {
        $sut = ProviderSettings::fromArray([
            'model_parameters' => ['temperature' => 0.5],
        ]);
        $result = $sut->toArray();
        static::assertArrayHasKey('model_parameters', $result);
        static::assertSame(['temperature' => 0.5], $result['model_parameters']);
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializesMatchesToArray(): void
    {
        $sut = ProviderSettings::fromArray([
            'model_parameters' => ['temperature' => 0.7],
            'adapter' => ['region' => 'eu-west-1'],
        ]);
        static::assertSame($sut->toArray(), $sut->jsonSerialize());
    }

    public function testItJsonSerializesViaJsonEncode(): void
    {
        $sut = ProviderSettings::fromArray(['adapter' => ['region' => 'eu-west-1']]);
        $encoded = json_encode($sut);
        static::assertSame(json_encode($sut->toArray()), $encoded);
    }

    // =========================================================================
    // Roundtrip
    // =========================================================================

    public function testItRoundtripsFromArrayToArray(): void
    {
        $input = [
            'model_parameters' => ['temperature' => 0.5, 'max_tokens' => 2048],
            'adapter' => ['region' => 'us-east-1'],
        ];
        $sut = ProviderSettings::fromArray($input);
        static::assertSame($input, $sut->toArray());
    }

    public function testItRoundtripsEmptyArrayToArray(): void
    {
        $sut = ProviderSettings::fromArray([]);
        // Empty model_parameters is omitted; result must be empty
        static::assertSame([], $sut->toArray());
    }
}
