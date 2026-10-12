<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\SettingsAndConfig\Values;

use App\Services\System\Database\SettingsAndConfig\Values\SettingsValueComparator;
use App\Services\Users\Settings\Values\Theme;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\SettingsAndConfig\Values\SettingsValueComparatorTest\SettingsValueComparatorTestFixtures\ComparatorTestSettings;

#[CoversClass(SettingsValueComparator::class)]
class SettingsValueComparatorTest extends TestCase
{
    private SettingsValueComparator $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new SettingsValueComparator();
    }

    // =========================================================================
    // testItConstructs
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(SettingsValueComparator::class, $this->sut);
    }

    #[DataProvider('provideTestItComparesPrimitiveValuesData')]
    public function testItComparesPrimitiveValues(mixed $left, mixed $right, bool $expected): void
    {
        self::assertSame($expected, $this->sut->valuesEqual($left, $right));
    }

    // =========================================================================
    // valuesEqual — primitives
    // =========================================================================

    public static function provideTestItComparesPrimitiveValuesData(): iterable
    {
        yield 'equal integers' => [1, 1, true];

        yield 'different integers' => [1, 2, false];

        yield 'integer vs string is different' => [1, '1', false];

        yield 'equal strings' => ['abc', 'abc', true];

        yield 'different strings' => ['abc', 'abd', false];

        yield 'equal booleans' => [true, true, true];

        yield 'boolean vs integer is different' => [true, 1, false];

        yield 'null vs null' => [null, null, true];

        yield 'null vs empty string' => [null, '', false];

        yield 'null vs value' => [null, 'x', false];
    }

    // =========================================================================
    // valuesEqual — structured types (the dragons)
    // =========================================================================

    public function testItComparesArraysOrderSensitively(): void
    {
        // D7: order matters — ['a','b'] and ['b','a'] are different values.
        self::assertTrue($this->sut->valuesEqual(['a', 'b'], ['a', 'b']));
        self::assertFalse($this->sut->valuesEqual(['a', 'b'], ['b', 'a']));
        self::assertFalse($this->sut->valuesEqual(['a'], ['a', 'b']));
        self::assertFalse($this->sut->valuesEqual(['a' => 1], ['b' => 1]));
    }

    public function testItComparesEnumsByIdentity(): void
    {
        self::assertTrue($this->sut->valuesEqual(Theme::Light, Theme::Light));
        self::assertFalse($this->sut->valuesEqual(Theme::Light, Theme::Dark));
    }

    public function testItComparesDatesByInstant(): void
    {
        $left = Carbon::parse('2024-01-15 10:00:00');
        $right = Carbon::parse('2024-01-15 10:00:00');
        $other = Carbon::parse('2024-01-15 10:00:01');

        self::assertTrue($this->sut->valuesEqual($left, $right));
        self::assertFalse($this->sut->valuesEqual($left, $other));
    }

    public function testItTreatsUnknownObjectsAsAlwaysDifferent(): void
    {
        // D6: custom-caster products cannot be compared — always changed.
        self::assertFalse($this->sut->valuesEqual(new \stdClass(), new \stdClass()));
        self::assertFalse($this->sut->valuesEqual(new \stdClass(), 'anything'));
    }

    public function testItComparesEncryptedPlaintextsNotCiphertexts(): void
    {
        // D1: serialized comparisons would see fresh random ciphertexts for equal
        // plaintexts — the typed comparison must see them as equal.
        $left = ComparatorTestSettings::fromArray(['secret' => 'sk-123']);
        $right = ComparatorTestSettings::fromArray(['secret' => 'sk-123']);
        $other = ComparatorTestSettings::fromArray(['secret' => 'sk-456']);

        // The serialized forms differ despite equal plaintexts.
        self::assertNotSame($left->toStringArray()['secret'], $right->toStringArray()['secret']);

        self::assertTrue($this->sut->valuesEqual($left->secret, $right->secret));
        self::assertFalse($this->sut->valuesEqual($left->secret, $other->secret));
    }

    public function testItComparesNestedCastablesRecursively(): void
    {
        // D2: object identity comparison would always see them as different.
        $left = ComparatorTestSettings::fromStringArray(['nested' => '{"street":"Main St","theme":"dark"}']);
        $right = ComparatorTestSettings::fromStringArray(['nested' => '{"street":"Main St","theme":"dark"}']);
        $other = ComparatorTestSettings::fromStringArray(['nested' => '{"street":"Main St","theme":"light"}']);

        self::assertTrue($this->sut->valuesEqual($left->nested, $right->nested));
        self::assertFalse($this->sut->valuesEqual($left->nested, $other->nested));
    }

    // =========================================================================
    // diffObjects
    // =========================================================================

    public function testItDiffObjectsReturnsOnlyDifferingProperties(): void
    {
        $left = ComparatorTestSettings::fromStringArray([
            'max_tokens' => '8192',
            'name' => 'left',
            'allowed' => '["a"]',
            'theme' => 'dark',
            'nested' => '{"street":"Main St","theme":"dark"}',
        ]);
        $right = ComparatorTestSettings::fromStringArray([
            'max_tokens' => '4096',
            'name' => 'left',
            'allowed' => '["a"]',
            'theme' => 'dark',
            'nested' => '{"street":"Main St","theme":"light"}',
        ]);

        $diff = $this->sut->diffObjects($left, $right);

        self::assertTrue($diff->differs());
        self::assertTrue($diff->isDifferent('max_tokens'));
        self::assertTrue($diff->isDifferent('nested'));
        self::assertFalse($diff->isDifferent('name'));
        self::assertFalse($diff->isDifferent('allowed'));
        self::assertFalse($diff->isDifferent('theme'));
    }

    public function testItDiffObjectsReturnsEmptyDiffForEqualObjects(): void
    {
        $left = ComparatorTestSettings::fromStringArray(['max_tokens' => '8192']);
        $right = ComparatorTestSettings::fromStringArray(['max_tokens' => '8192']);

        self::assertFalse($this->sut->diffObjects($left, $right)->differs());
    }

    public function testItDiffObjectsAgainstDefaultsCatchesCustomizedProperties(): void
    {
        $current = ComparatorTestSettings::fromStringArray(['max_tokens' => '8192']);
        $defaults = ComparatorTestSettings::fromStringArray([]);

        $diff = $this->sut->diffObjects($current, $defaults);

        self::assertTrue($diff->isDifferent('max_tokens'));
        self::assertFalse($diff->isDifferent('name'));
        self::assertFalse($diff->isDifferent('theme'));
    }

    public function testItDiffObjectsHandlesUninitializedProperties(): void
    {
        // created_at stays null, nested keeps its (uninitialized) default — the
        // comparator must not throw on uninitialized typed properties.
        $left = ComparatorTestSettings::fromStringArray([]);
        $right = ComparatorTestSettings::fromStringArray([]);

        self::assertFalse($this->sut->diffObjects($left, $right)->differs());
    }
}
