<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Models\Limits\Values;

use App\Services\Ai\Models\Limits\AiModelLimitsInterface;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(NullAiModelLimits::class)]
class NullAiModelLimitsTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = NullAiModelLimits::fromArray([]);
        static::assertInstanceOf(NullAiModelLimits::class, $sut);
    }

    // =========================================================================
    // Interface contract
    // =========================================================================

    public function testItImplementsAiModelLimitsInterface(): void
    {
        $sut = NullAiModelLimits::fromArray([]);
        static::assertInstanceOf(AiModelLimitsInterface::class, $sut);
    }

    // =========================================================================
    // fromArray
    // =========================================================================

    public function testItFromArrayIgnoresInputData(): void
    {
        // Input data is irrelevant — the null object carries no state.
        $sut = NullAiModelLimits::fromArray(['max_input_tokens' => 9999, 'max_output_tokens' => 8888]);
        static::assertInstanceOf(NullAiModelLimits::class, $sut);
    }

    public function testItFromArrayReturnsNewInstanceEachTime(): void
    {
        $a = NullAiModelLimits::fromArray([]);
        $b = NullAiModelLimits::fromArray([]);
        static::assertNotSame($a, $b);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItToArrayReturnsEmptyArray(): void
    {
        $sut = NullAiModelLimits::fromArray([]);
        static::assertSame([], $sut->toArray());
    }
}
