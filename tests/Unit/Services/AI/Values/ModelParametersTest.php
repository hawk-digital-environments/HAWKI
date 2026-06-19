<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Values;

use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\WellKnownModelParams;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelParameters::class)]
class ModelParametersTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertInstanceOf(ModelParameters::class, $sut);
    }

    // =========================================================================
    // Temperature
    // =========================================================================

    public function testItGetsTemperature(): void
    {
        $sut = ModelParameters::fromArray(['temperature' => 0.7]);
        static::assertSame(0.7, $sut->getTemperature());
    }

    public function testItGetsTemperatureCastToFloat(): void
    {
        $sut = ModelParameters::fromArray(['temperature' => '0.5']);
        static::assertSame(0.5, $sut->getTemperature());
    }

    public function testItGetsTemperatureCustomDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(0.3, $sut->getTemperature(0.3));
    }

    public function testItGetsTemperatureBuiltInDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(0.95, $sut->getTemperature());
    }

    public function testItSetsTemperature(): void
    {
        $sut = ModelParameters::fromArray([]);
        $sut->setTemperature(0.42);
        static::assertSame(0.42, $sut->getTemperature());
    }

    public function testItSetTemperatureReturnsSelf(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setTemperature(0.5));
    }

    // =========================================================================
    // Top-P
    // =========================================================================

    public function testItGetsTopP(): void
    {
        $sut = ModelParameters::fromArray(['top_p' => 0.9]);
        static::assertSame(0.9, $sut->getTopP());
    }

    public function testItGetsTopPCastToFloat(): void
    {
        $sut = ModelParameters::fromArray(['top_p' => '0.8']);
        static::assertSame(0.8, $sut->getTopP());
    }

    public function testItGetsTopPCustomDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(0.5, $sut->getTopP(0.5));
    }

    public function testItGetsTopPBuiltInDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(1.0, $sut->getTopP());
    }

    public function testItSetsTopP(): void
    {
        $sut = ModelParameters::fromArray([]);
        $sut->setTopP(0.75);
        static::assertSame(0.75, $sut->getTopP());
    }

    public function testItSetTopPReturnsSelf(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setTopP(0.9));
    }

    // =========================================================================
    // Max tokens
    // =========================================================================

    public function testItGetsMaxTokens(): void
    {
        $sut = ModelParameters::fromArray(['max_tokens' => 1024]);
        static::assertSame(1024, $sut->getMaxTokens());
    }

    public function testItGetsMaxTokensCastToInt(): void
    {
        $sut = ModelParameters::fromArray(['max_tokens' => '2048']);
        static::assertSame(2048, $sut->getMaxTokens());
    }

    public function testItGetsMaxTokensCustomDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(512, $sut->getMaxTokens(512));
    }

    public function testItGetsMaxTokensBuiltInDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(4096, $sut->getMaxTokens());
    }

    public function testItSetsMaxTokens(): void
    {
        $sut = ModelParameters::fromArray([]);
        $sut->setMaxTokens(8192);
        static::assertSame(8192, $sut->getMaxTokens());
    }

    public function testItSetMaxTokensReturnsSelf(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setMaxTokens(1024));
    }

    // =========================================================================
    // Max thinking tokens
    // =========================================================================

    public function testItGetsMaxThinkingTokens(): void
    {
        $sut = ModelParameters::fromArray(['max_thinking_tokens' => 512]);
        static::assertSame(512, $sut->getMaxThinkingTokens());
    }

    public function testItGetsMaxThinkingTokensCastToInt(): void
    {
        $sut = ModelParameters::fromArray(['max_thinking_tokens' => '1024']);
        static::assertSame(1024, $sut->getMaxThinkingTokens());
    }

    public function testItGetsMaxThinkingTokensCustomDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(256, $sut->getMaxThinkingTokens(256));
    }

    public function testItGetsMaxThinkingTokensBuiltInDefaultWhenAbsent(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame(2048, $sut->getMaxThinkingTokens());
    }

    public function testItSetsMaxThinkingTokens(): void
    {
        $sut = ModelParameters::fromArray([]);
        $sut->setMaxThinkingTokens(4096);
        static::assertSame(4096, $sut->getMaxThinkingTokens());
    }

    public function testItSetMaxThinkingTokensReturnsSelf(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setMaxThinkingTokens(2048));
    }

    // =========================================================================
    // Generic key-value access
    // =========================================================================

    public function testItReportsHasForPresentKey(): void
    {
        $sut = ModelParameters::fromArray(['custom' => 'value']);
        static::assertTrue($sut->has('custom'));
    }

    public function testItReportsHasForPresentWellKnownKey(): void
    {
        $sut = ModelParameters::fromArray([WellKnownModelParams::TEMPERATURE => 0.7]);
        static::assertTrue($sut->has(WellKnownModelParams::TEMPERATURE));
    }

    public function testItReportsHasForAbsentKey(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertFalse($sut->has('custom'));
    }

    public function testItGetsValueForPresentKey(): void
    {
        $sut = ModelParameters::fromArray(['custom' => 'hello']);
        static::assertSame('hello', $sut->get('custom'));
    }

    public function testItGetsDefaultForAbsentKey(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame('fallback', $sut->get('missing', 'fallback'));
    }

    public function testItGetsNullForAbsentKeyWithNoDefault(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertNull($sut->get('missing'));
    }

    public function testItSetsValue(): void
    {
        $sut = ModelParameters::fromArray([]);
        $sut->set('new_key', 42);
        static::assertSame(42, $sut->get('new_key'));
    }

    public function testItSetReturnsSelf(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame($sut, $sut->set('key', 'value'));
    }

    public function testItRemovesKey(): void
    {
        $sut = ModelParameters::fromArray(['key' => 'value']);
        $sut->remove('key');
        static::assertFalse($sut->has('key'));
    }

    public function testItRemoveReturnsSelf(): void
    {
        $sut = ModelParameters::fromArray(['key' => 'value']);
        static::assertSame($sut, $sut->remove('key'));
    }

    public function testItRemoveIsNoOpForAbsentKey(): void
    {
        $sut = ModelParameters::fromArray([]);
        $sut->remove('nonexistent');
        static::assertFalse($sut->has('nonexistent'));
    }

    // =========================================================================
    // mergeWith
    // =========================================================================

    public function testItMergesDisjointParameters(): void
    {
        $a = ModelParameters::fromArray(['temperature' => 0.5, 'stream' => true]);
        $b = ModelParameters::fromArray(['top_p' => 0.9, 'debug' => false]);

        $merged = $a->mergeWith($b);

        static::assertSame(0.5, $merged->getTemperature());
        static::assertSame(0.9, $merged->getTopP());
        static::assertTrue($merged->get('stream'));
        static::assertFalse($merged->get('debug'));
    }

    public function testItMergeWithOtherTakesPrecedenceOnConflict(): void
    {
        $a = ModelParameters::fromArray(['temperature' => 0.5]);
        $b = ModelParameters::fromArray(['temperature' => 0.9]);

        $merged = $a->mergeWith($b);

        static::assertSame(0.9, $merged->getTemperature());
    }

    public function testItMergeWithReturnsNewInstance(): void
    {
        $a = ModelParameters::fromArray(['temperature' => 0.5]);
        $b = ModelParameters::fromArray(['top_p' => 0.9]);

        $merged = $a->mergeWith($b);

        static::assertNotSame($a, $merged);
        static::assertNotSame($b, $merged);
    }

    public function testItMergeWithDoesNotMutateOriginals(): void
    {
        $a = ModelParameters::fromArray(['temperature' => 0.5]);
        $b = ModelParameters::fromArray(['temperature' => 0.9]);

        $a->mergeWith($b);

        static::assertSame(0.5, $a->getTemperature());
        static::assertSame(0.9, $b->getTemperature());
    }

    // =========================================================================
    // toArray / toAdditionalArray / jsonSerialize
    // =========================================================================

    public function testItConvertsToArray(): void
    {
        $data = ['temperature' => 0.7, 'top_p' => 0.9, 'extra' => true];
        $sut = ModelParameters::fromArray($data);
        static::assertSame($data, $sut->toArray());
    }

    public function testItConvertsEmptyToArray(): void
    {
        $sut = ModelParameters::fromArray([]);
        static::assertSame([], $sut->toArray());
    }

    public function testItToAdditionalArrayExcludesWellKnownKeys(): void
    {
        $sut = ModelParameters::fromArray([
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 1024,
            'max_thinking_tokens' => 512,
            'stream' => true,
            'provider_extra' => 'yes',
        ]);

        static::assertSame(
            ['stream' => true, 'provider_extra' => 'yes'],
            $sut->toAdditionalArray()
        );
    }

    public function testItToAdditionalArrayIsEmptyWhenOnlyWellKnownKeysPresent(): void
    {
        $sut = ModelParameters::fromArray([
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 1024,
            'max_thinking_tokens' => 512,
        ]);

        static::assertSame([], $sut->toAdditionalArray());
    }

    public function testItToAdditionalArrayDoesNotMutateOriginalData(): void
    {
        $sut = ModelParameters::fromArray([
            'temperature' => 0.7,
            'extra' => 'present',
        ]);

        $sut->toAdditionalArray();

        static::assertSame(0.7, $sut->getTemperature());
        static::assertSame('present', $sut->get('extra'));
    }

    public function testItJsonSerializesMatchesToArray(): void
    {
        $data = ['temperature' => 0.7, 'top_p' => 0.9, 'extra' => 'value'];
        $sut = ModelParameters::fromArray($data);
        static::assertSame($sut->toArray(), $sut->jsonSerialize());
    }

    public function testItJsonSerializesViaJsonEncode(): void
    {
        $data = ['temperature' => 0.7, 'max_tokens' => 1024];
        $sut = ModelParameters::fromArray($data);
        static::assertSame(json_encode($data), json_encode($sut));
    }

    // =========================================================================
    // Roundtrip
    // =========================================================================

    public function testItRoundtripsFromArrayToArray(): void
    {
        $data = [
            'temperature' => 0.5,
            'top_p' => 1.0,
            'max_tokens' => 2048,
            'max_thinking_tokens' => 256,
            'extra' => 'x',
        ];
        $sut = ModelParameters::fromArray($data);
        static::assertSame($data, $sut->toArray());
    }
}
