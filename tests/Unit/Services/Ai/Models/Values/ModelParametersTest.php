<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Models\Values;

use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Parameters\Values\WellKnownModelParams;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AiModelParameters::class)]
class ModelParametersTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertInstanceOf(AiModelParameters::class, $sut);
    }

    // =========================================================================
    // Temperature
    // =========================================================================

    public function testItGetsTemperature(): void
    {
        $sut = AiModelParameters::fromArray(['temperature' => 0.7]);
        static::assertSame(0.7, $sut->getTemperature());
    }

    public function testItGetsTemperatureCastToFloat(): void
    {
        $sut = AiModelParameters::fromArray(['temperature' => '0.5']);
        static::assertSame(0.5, $sut->getTemperature());
    }

    public function testItGetsTemperatureCustomDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(0.3, $sut->getTemperature(0.3));
    }

    public function testItGetsTemperatureBuiltInDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(0.95, $sut->getTemperature());
    }

    public function testItSetsTemperature(): void
    {
        $sut = AiModelParameters::fromArray([]);
        $sut->setTemperature(0.42);
        static::assertSame(0.42, $sut->getTemperature());
    }

    public function testItSetTemperatureReturnsSelf(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setTemperature(0.5));
    }

    // =========================================================================
    // Top-P
    // =========================================================================

    public function testItGetsTopP(): void
    {
        $sut = AiModelParameters::fromArray(['top_p' => 0.9]);
        static::assertSame(0.9, $sut->getTopP());
    }

    public function testItGetsTopPCastToFloat(): void
    {
        $sut = AiModelParameters::fromArray(['top_p' => '0.8']);
        static::assertSame(0.8, $sut->getTopP());
    }

    public function testItGetsTopPCustomDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(0.5, $sut->getTopP(0.5));
    }

    public function testItGetsTopPBuiltInDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(1.0, $sut->getTopP());
    }

    public function testItSetsTopP(): void
    {
        $sut = AiModelParameters::fromArray([]);
        $sut->setTopP(0.75);
        static::assertSame(0.75, $sut->getTopP());
    }

    public function testItSetTopPReturnsSelf(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setTopP(0.9));
    }

    // =========================================================================
    // Max tokens
    // =========================================================================

    public function testItGetsMaxTokens(): void
    {
        $sut = AiModelParameters::fromArray(['max_tokens' => 1024]);
        static::assertSame(1024, $sut->getMaxTokens());
    }

    public function testItGetsMaxTokensCastToInt(): void
    {
        $sut = AiModelParameters::fromArray(['max_tokens' => '2048']);
        static::assertSame(2048, $sut->getMaxTokens());
    }

    public function testItGetsMaxTokensCustomDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(512, $sut->getMaxTokens(512));
    }

    public function testItGetsMaxTokensBuiltInDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(4096, $sut->getMaxTokens());
    }

    public function testItSetsMaxTokens(): void
    {
        $sut = AiModelParameters::fromArray([]);
        $sut->setMaxTokens(8192);
        static::assertSame(8192, $sut->getMaxTokens());
    }

    public function testItSetMaxTokensReturnsSelf(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setMaxTokens(1024));
    }

    // =========================================================================
    // Max thinking tokens
    // =========================================================================

    public function testItGetsMaxThinkingTokens(): void
    {
        $sut = AiModelParameters::fromArray(['max_thinking_tokens' => 512]);
        static::assertSame(512, $sut->getMaxThinkingTokens());
    }

    public function testItGetsMaxThinkingTokensCastToInt(): void
    {
        $sut = AiModelParameters::fromArray(['max_thinking_tokens' => '1024']);
        static::assertSame(1024, $sut->getMaxThinkingTokens());
    }

    public function testItGetsMaxThinkingTokensCustomDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(256, $sut->getMaxThinkingTokens(256));
    }

    public function testItGetsMaxThinkingTokensBuiltInDefaultWhenAbsent(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame(2048, $sut->getMaxThinkingTokens());
    }

    public function testItSetsMaxThinkingTokens(): void
    {
        $sut = AiModelParameters::fromArray([]);
        $sut->setMaxThinkingTokens(4096);
        static::assertSame(4096, $sut->getMaxThinkingTokens());
    }

    public function testItSetMaxThinkingTokensReturnsSelf(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame($sut, $sut->setMaxThinkingTokens(2048));
    }

    // =========================================================================
    // Generic key-value access
    // =========================================================================

    public function testItReportsHasForPresentKey(): void
    {
        $sut = AiModelParameters::fromArray(['custom' => 'value']);
        static::assertTrue($sut->has('custom'));
    }

    public function testItReportsHasForPresentWellKnownKey(): void
    {
        $sut = AiModelParameters::fromArray([WellKnownModelParams::TEMPERATURE => 0.7]);
        static::assertTrue($sut->has(WellKnownModelParams::TEMPERATURE));
    }

    public function testItReportsHasForAbsentKey(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertFalse($sut->has('custom'));
    }

    public function testItGetsValueForPresentKey(): void
    {
        $sut = AiModelParameters::fromArray(['custom' => 'hello']);
        static::assertSame('hello', $sut->get('custom'));
    }

    public function testItGetsDefaultForAbsentKey(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame('fallback', $sut->get('missing', 'fallback'));
    }

    public function testItGetsNullForAbsentKeyWithNoDefault(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertNull($sut->get('missing'));
    }

    public function testItSetsValue(): void
    {
        $sut = AiModelParameters::fromArray([]);
        $sut->set('new_key', 42);
        static::assertSame(42, $sut->get('new_key'));
    }

    public function testItSetReturnsSelf(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame($sut, $sut->set('key', 'value'));
    }

    public function testItRemovesKey(): void
    {
        $sut = AiModelParameters::fromArray(['key' => 'value']);
        $sut->remove('key');
        static::assertFalse($sut->has('key'));
    }

    public function testItRemoveReturnsSelf(): void
    {
        $sut = AiModelParameters::fromArray(['key' => 'value']);
        static::assertSame($sut, $sut->remove('key'));
    }

    public function testItRemoveIsNoOpForAbsentKey(): void
    {
        $sut = AiModelParameters::fromArray([]);
        $sut->remove('nonexistent');
        static::assertFalse($sut->has('nonexistent'));
    }

    // =========================================================================
    // mergeWith
    // =========================================================================

    public function testItMergesDisjointParameters(): void
    {
        $a = AiModelParameters::fromArray(['temperature' => 0.5, 'stream' => true]);
        $b = AiModelParameters::fromArray(['top_p' => 0.9, 'debug' => false]);

        $merged = $a->mergeWith($b);

        static::assertSame(0.5, $merged->getTemperature());
        static::assertSame(0.9, $merged->getTopP());
        static::assertTrue($merged->get('stream'));
        static::assertFalse($merged->get('debug'));
    }

    public function testItMergeWithOtherTakesPrecedenceOnConflict(): void
    {
        $a = AiModelParameters::fromArray(['temperature' => 0.5]);
        $b = AiModelParameters::fromArray(['temperature' => 0.9]);

        $merged = $a->mergeWith($b);

        static::assertSame(0.9, $merged->getTemperature());
    }

    public function testItMergeWithReturnsNewInstance(): void
    {
        $a = AiModelParameters::fromArray(['temperature' => 0.5]);
        $b = AiModelParameters::fromArray(['top_p' => 0.9]);

        $merged = $a->mergeWith($b);

        static::assertNotSame($a, $merged);
        static::assertNotSame($b, $merged);
    }

    public function testItMergeWithDoesNotMutateOriginals(): void
    {
        $a = AiModelParameters::fromArray(['temperature' => 0.5]);
        $b = AiModelParameters::fromArray(['temperature' => 0.9]);

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
        $sut = AiModelParameters::fromArray($data);
        static::assertSame($data, $sut->toArray());
    }

    public function testItConvertsEmptyToArray(): void
    {
        $sut = AiModelParameters::fromArray([]);
        static::assertSame([], $sut->toArray());
    }

    public function testItToAdditionalArrayExcludesWellKnownKeys(): void
    {
        $sut = AiModelParameters::fromArray([
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
        $sut = AiModelParameters::fromArray([
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 1024,
            'max_thinking_tokens' => 512,
        ]);

        static::assertSame([], $sut->toAdditionalArray());
    }

    public function testItToAdditionalArrayDoesNotMutateOriginalData(): void
    {
        $sut = AiModelParameters::fromArray([
            'temperature' => 0.7,
            'extra' => 'present',
        ]);

        $sut->toAdditionalArray();

        static::assertSame(0.7, $sut->getTemperature());
        static::assertSame('present', $sut->get('extra'));
    }

    public function testItJsonSerializesAlwaysIncludesWellKnownDefaults(): void
    {
        $sut = AiModelParameters::fromArray(['temperature' => 0.7, 'top_p' => 0.9, 'extra' => 'value']);
        $serialized = $sut->jsonSerialize();
        static::assertSame(0.7, $serialized['temperature']);
        static::assertSame(0.9, $serialized['top_p']);
        static::assertSame(4096, $serialized['max_tokens']);
        static::assertSame(2048, $serialized['max_thinking_tokens']);
        static::assertSame('value', $serialized['extra']);
    }

    public function testItJsonSerializesViaJsonEncode(): void
    {
        $sut = AiModelParameters::fromArray(['temperature' => 0.7, 'max_tokens' => 1024]);
        $expected = [
            'temperature' => 0.7,
            'top_p' => 1.0,
            'max_tokens' => 1024,
            'max_thinking_tokens' => 2048,
        ];
        static::assertSame(json_encode($expected), json_encode($sut));
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
        $sut = AiModelParameters::fromArray($data);
        static::assertSame($data, $sut->toArray());
    }
}
