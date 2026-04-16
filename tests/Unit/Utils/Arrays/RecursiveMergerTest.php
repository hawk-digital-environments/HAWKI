<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Arrays;

use App\Utils\Arrays\RecursiveMergeOption;
use App\Utils\Arrays\RecursiveMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RecursiveMerger::class)]
#[CoversClass(RecursiveMergeOption::class)]
class RecursiveMergerTest extends TestCase
{
    // =========================================================================
    // Basic merging
    // =========================================================================

    public function testItMergesTwoEmptyArrays(): void
    {
        $result = RecursiveMerger::merge([], []);
        static::assertSame([], $result);
    }

    public function testItReturnsBWhenAIsEmpty(): void
    {
        $result = RecursiveMerger::merge([], ['foo' => 'bar']);
        static::assertSame(['foo' => 'bar'], $result);
    }

    public function testItReturnsAWhenBIsEmpty(): void
    {
        $result = RecursiveMerger::merge(['foo' => 'bar'], []);
        static::assertSame(['foo' => 'bar'], $result);
    }

    public function testItReturnsAWhenBHasEmptyChild(): void
    {
        $result = RecursiveMerger::merge(['foo' => 'bar', 'faz' => ['bar']], ['faz' => []]);
        static::assertSame(['foo' => 'bar', 'faz' => ['bar']], $result);
    }

    public function testItOverwritesStringKeyWithValueFromB(): void
    {
        $result = RecursiveMerger::merge(['key' => 'original'], ['key' => 'overwritten']);
        static::assertSame(['key' => 'overwritten'], $result);
    }

    public function testItAddsNewStringKeyFromB(): void
    {
        $result = RecursiveMerger::merge(['a' => 1], ['b' => 2]);
        static::assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testItMergesStringKeyedArraysRecursively(): void
    {
        $a = ['nested' => ['x' => 1, 'y' => 2]];
        $b = ['nested' => ['y' => 99, 'z' => 3]];
        $result = RecursiveMerger::merge($a, $b);
        static::assertSame(['nested' => ['x' => 1, 'y' => 99, 'z' => 3]], $result);
    }

    public function testItAppendsNumericScalarValuesByDefault(): void
    {
        // Non-array values at numeric keys are appended, not overwritten
        $result = RecursiveMerger::merge(['a', 'b'], ['c', 'd']);
        static::assertSame(['a', 'b', 'c', 'd'], $result);
    }

    public function testItMergesNumericKeyedArraysRecursivelyByDefault(): void
    {
        // Array values at numeric keys are merged recursively, not appended
        $a = [['x' => 1]];
        $b = [['y' => 2]];
        $result = RecursiveMerger::merge($a, $b);
        static::assertSame([['x' => 1, 'y' => 2]], $result);
    }

    public function testItMergesMultipleArraysInSequence(): void
    {
        $result = RecursiveMerger::merge(['a' => 1], ['b' => 2], ['c' => 3]);
        static::assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function testItAppliesLaterArraysOverEarlierOnesInSequence(): void
    {
        $result = RecursiveMerger::merge(['key' => 'first'], ['key' => 'second'], ['key' => 'third']);
        static::assertSame(['key' => 'third'], $result);
    }

    // =========================================================================
    // NO_NUMERIC_MERGE option
    // =========================================================================

    public function testItAppendsNumericKeyedArraysWithNoNumericMerge(): void
    {
        // With NO_NUMERIC_MERGE, array values at numeric keys are also appended (not recursively merged)
        $result = RecursiveMerger::merge([['a']], [['b']], RecursiveMergeOption::NO_NUMERIC_MERGE);
        static::assertSame([['a'], ['b']], $result);
    }

    public function testItAppendsNumericScalarsWithNoNumericMerge(): void
    {
        $result = RecursiveMerger::merge(['x', 'y'], ['z'], RecursiveMergeOption::NO_NUMERIC_MERGE);
        static::assertSame(['x', 'y', 'z'], $result);
    }

    public function testItStillMergesStringKeysWithNoNumericMerge(): void
    {
        $result = RecursiveMerger::merge(['a' => 1], ['a' => 2, 'b' => 3], RecursiveMergeOption::NO_NUMERIC_MERGE);
        static::assertSame(['a' => 2, 'b' => 3], $result);
    }

    // =========================================================================
    // STRICT_NUMERIC_MERGE option
    // =========================================================================

    public function testItOverwritesNumericScalarsWithStrictNumericMerge(): void
    {
        // With STRICT_NUMERIC_MERGE, scalar values at numeric keys are overwritten, not appended
        $result = RecursiveMerger::merge(['a', 'b'], ['c'], RecursiveMergeOption::STRICT_NUMERIC_MERGE);
        static::assertSame(['c', 'b'], $result);
    }

    public function testItMergesNumericKeyedArraysDeepWithStrictNumericMerge(): void
    {
        // With STRICT_NUMERIC_MERGE, arrays at numeric keys are still recursively merged
        $a = [['x' => 1, 'y' => 2]];
        $b = [['x' => 99, 'z' => 3]];
        $result = RecursiveMerger::merge($a, $b, RecursiveMergeOption::STRICT_NUMERIC_MERGE);
        static::assertSame([['x' => 99, 'y' => 2, 'z' => 3]], $result);
    }

    // =========================================================================
    // ALLOW_REMOVAL option
    // =========================================================================

    public function testItRemovesKeyWhenValueIsUnsetSentinel(): void
    {
        $result = RecursiveMerger::merge(
            ['a' => 1, 'b' => 2],
            ['b' => '__UNSET'],
            RecursiveMergeOption::ALLOW_REMOVAL
        );
        static::assertSame(['a' => 1], $result);
    }

    public function testItKeepsUnsetSentinelValueWithoutAllowRemoval(): void
    {
        // Without ALLOW_REMOVAL, '__UNSET' is treated as a regular value
        $result = RecursiveMerger::merge(['a' => 1], ['b' => '__UNSET']);
        static::assertSame(['a' => 1, 'b' => '__UNSET'], $result);
    }

    public function testItIgnoresUnsetSentinelForNonExistentKey(): void
    {
        // Removing a key that does not exist in $a is a no-op
        $result = RecursiveMerger::merge(
            ['a' => 1],
            ['missing' => '__UNSET'],
            RecursiveMergeOption::ALLOW_REMOVAL
        );
        static::assertSame(['a' => 1], $result);
    }

    public function testItStillMergesRegularValuesWithAllowRemoval(): void
    {
        $result = RecursiveMerger::merge(
            ['a' => 1],
            ['b' => 2],
            RecursiveMergeOption::ALLOW_REMOVAL
        );
        static::assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testItFiltersOutUnsetSentinelsWhenAIsEmpty(): void
    {
        // With ALLOW_REMOVAL the early-return shortcut for empty $a is skipped,
        // so '__UNSET' entries in $b are processed and absent from the result
        $result = RecursiveMerger::merge(
            [],
            ['a' => '__UNSET', 'b' => 2],
            RecursiveMergeOption::ALLOW_REMOVAL
        );
        static::assertSame(['b' => 2], $result);
    }
}
