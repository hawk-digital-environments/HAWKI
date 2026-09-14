<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Values;

use App\Services\Ai\Providers\Adapters\Values\AnthropicThinkingConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AnthropicThinkingConfig::class)]
class AnthropicThinkingConfigTest extends TestCase
{
    public function testItConstructs(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 64_000, null, null);

        self::assertInstanceOf(AnthropicThinkingConfig::class, $sut);
    }

    public function testItEnablesThinkingWithinTheHalfOutputLimit(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 64_000, null, null);

        self::assertTrue($sut->enabled);
        self::assertSame(['type' => 'enabled', 'budget_tokens' => 2_048], $sut->thinking);
        self::assertSame([], $sut->samplingOverrides);
        self::assertSame([], $sut->warnings);
    }

    public function testItCapsTheBudgetAtHalfTheOutputLimit(): void
    {
        $sut = AnthropicThinkingConfig::from(6_000, 8_192, null, null);

        self::assertTrue($sut->enabled);
        self::assertSame(4_096, $sut->thinking['budget_tokens']);
        self::assertSame([], $sut->warnings);
    }

    public function testItDisablesThinkingWhenTheRequestedBudgetIsBelowTheMinimum(): void
    {
        $sut = AnthropicThinkingConfig::from(512, 64_000, null, null);

        self::assertFalse($sut->enabled);
        self::assertSame([], $sut->thinking);
        self::assertCount(1, $sut->warnings);
        self::assertStringContainsString('below Anthropic\'s minimum', $sut->warnings[0]);
    }

    public function testItDisablesThinkingWhenHalfTheOutputLimitIsBelowTheMinimum(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 1_024, null, null);

        self::assertFalse($sut->enabled);
        self::assertSame([], $sut->thinking);
        self::assertCount(1, $sut->warnings);
        self::assertStringContainsString('half of the 1024 max output tokens', $sut->warnings[0]);
    }

    public function testItNeutralisesTemperatureAndWarns(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 64_000, 0.3, null);

        self::assertTrue($sut->enabled);
        self::assertSame(['temperature' => 1.0], $sut->samplingOverrides);
        self::assertTrue($sut->overridesSampling);
        self::assertCount(1, $sut->warnings);
        self::assertStringContainsString('temperature 0.30 neutralised', $sut->warnings[0]);
    }

    public function testItKeepsTemperatureOneWithoutWarning(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 64_000, 1.0, null);

        self::assertTrue($sut->enabled);
        self::assertSame(['temperature' => 1.0], $sut->samplingOverrides);
        self::assertSame([], $sut->warnings);
    }

    public function testItNeutralisesLowTopPAndWarns(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 64_000, null, 0.5);

        self::assertTrue($sut->enabled);
        self::assertSame(['top_p' => 1.0], $sut->samplingOverrides);
        self::assertCount(1, $sut->warnings);
        self::assertStringContainsString('top-p 0.50 neutralised', $sut->warnings[0]);
    }

    public function testItKeepsHighTopPWithoutWarning(): void
    {
        $sut = AnthropicThinkingConfig::from(2_048, 64_000, null, 0.95);

        self::assertTrue($sut->enabled);
        self::assertSame([], $sut->samplingOverrides);
        self::assertSame([], $sut->warnings);
    }
}
