<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Models\Pricing\Values;

use App\Services\Ai\Models\Pricing\AiModelPricingInterface;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NullPricing::class)]
class NullPricingTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = NullPricing::fromArray([]);
        static::assertInstanceOf(NullPricing::class, $sut);
    }

    // =========================================================================
    // Interface contract
    // =========================================================================

    public function testItImplementsAiModelPricingInterface(): void
    {
        $sut = NullPricing::fromArray([]);
        static::assertInstanceOf(AiModelPricingInterface::class, $sut);
    }

    // =========================================================================
    // isUndefined
    // =========================================================================

    public function testItIsUndefined(): void
    {
        // The null object always signals "no pricing data available".
        $sut = NullPricing::fromArray([]);
        static::assertTrue($sut->isUndefined());
    }

    // =========================================================================
    // isFree
    // =========================================================================

    public function testItIsNotFree(): void
    {
        // Unknown pricing must never be treated as zero cost.
        $sut = NullPricing::fromArray([]);
        static::assertFalse($sut->isFree());
    }

    // =========================================================================
    // fromArray
    // =========================================================================

    public function testItFromArrayIgnoresInputData(): void
    {
        // The null object carries no state; any data passed is discarded.
        $sut = NullPricing::fromArray(['ranges' => [['input_cost_per_token' => 0.001]]]);
        static::assertTrue($sut->isUndefined());
    }

    public function testItFromArrayReturnsNewInstanceEachTime(): void
    {
        $a = NullPricing::fromArray([]);
        $b = NullPricing::fromArray([]);
        static::assertNotSame($a, $b);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItToArrayReturnsEmptyArray(): void
    {
        $sut = NullPricing::fromArray([]);
        static::assertSame([], $sut->toArray());
    }
}
