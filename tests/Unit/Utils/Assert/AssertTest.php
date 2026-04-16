<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Assert;

use App\Utils\Assert\Assert;
use App\Utils\Assert\Exceptions\InvalidArrayOfTypesException;
use App\Utils\Assert\Exceptions\InvalidCustomAssertionException;
use App\Utils\Assert\Exceptions\InvalidRequiredNonNegativeIntegerException;
use App\Utils\Assert\Exceptions\InvalidRequiredPositiveIntegerException;
use App\Utils\Assert\Exceptions\InvalidRequiredStringException;
use App\Utils\Assert\Exceptions\InvalidUriException;
use App\Utils\Assert\Exceptions\ValueIsNotInListException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Utils\Assert\AssertTestFixtures\SampleObject;

#[CoversClass(Assert::class)]
#[CoversClass(InvalidRequiredPositiveIntegerException::class)]
#[CoversClass(InvalidRequiredNonNegativeIntegerException::class)]
#[CoversClass(InvalidRequiredStringException::class)]
#[CoversClass(InvalidUriException::class)]
#[CoversClass(ValueIsNotInListException::class)]
#[CoversClass(InvalidArrayOfTypesException::class)]
#[CoversClass(InvalidCustomAssertionException::class)]
class AssertTest extends TestCase
{
    // =========================================================================
    // isPositiveInteger
    // =========================================================================

    public static function provideTestItPassesForPositiveIntegerData(): iterable
    {
        yield 'one' => [1];
        yield 'large number' => [PHP_INT_MAX];
    }

    #[DataProvider('provideTestItPassesForPositiveIntegerData')]
    public function testItPassesForPositiveInteger(int $value): void
    {
        Assert::isPositiveInteger($value);
        static::assertTrue(true);
    }

    public static function provideTestItFailsForInvalidPositiveIntegerData(): iterable
    {
        yield 'zero' => [0];
        yield 'negative integer' => [-1];
        yield 'float' => [1.5];
        yield 'string digit' => ['1'];
        yield 'null' => [null];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'empty array' => [[]];
    }

    #[DataProvider('provideTestItFailsForInvalidPositiveIntegerData')]
    public function testItFailsForInvalidPositiveInteger(mixed $value): void
    {
        $this->expectException(InvalidRequiredPositiveIntegerException::class);
        Assert::withKeyPrefix('test', fn() => Assert::isPositiveInteger($value, 'field'));
    }

    public function testItIncludesKeyInPositiveIntegerExceptionMessage(): void
    {
        $this->expectException(InvalidRequiredPositiveIntegerException::class);
        $this->expectExceptionMessage("Expected a positive integer for key 'test.field', got Integer(0)");
        Assert::withKeyPrefix('test', fn() => Assert::isPositiveInteger(0, 'field'));
    }

    // =========================================================================
    // isNonNegativeInteger
    // =========================================================================

    public static function provideTestItPassesForNonNegativeIntegerData(): iterable
    {
        yield 'zero' => [0];
        yield 'positive' => [42];
    }

    #[DataProvider('provideTestItPassesForNonNegativeIntegerData')]
    public function testItPassesForNonNegativeInteger(int $value): void
    {
        Assert::isNonNegativeInteger($value);
        static::assertTrue(true);
    }

    public static function provideTestItFailsForInvalidNonNegativeIntegerData(): iterable
    {
        yield 'negative integer' => [-1];
        yield 'float' => [0.5];
        yield 'string zero' => ['0'];
        yield 'null' => [null];
        yield 'false' => [false];
    }

    #[DataProvider('provideTestItFailsForInvalidNonNegativeIntegerData')]
    public function testItFailsForInvalidNonNegativeInteger(mixed $value): void
    {
        $this->expectException(InvalidRequiredNonNegativeIntegerException::class);
        Assert::withKeyPrefix('test', fn() => Assert::isNonNegativeInteger($value, 'field'));
    }

    public function testItIncludesKeyInNonNegativeIntegerExceptionMessage(): void
    {
        $this->expectException(InvalidRequiredNonNegativeIntegerException::class);
        $this->expectExceptionMessage("Expected a non-negative integer for key 'test.field', got Integer(-1)");
        Assert::withKeyPrefix('test', fn() => Assert::isNonNegativeInteger(-1, 'field'));
    }

    // =========================================================================
    // isNonEmptyString
    // =========================================================================

    public static function provideTestItPassesForNonEmptyStringData(): iterable
    {
        yield 'regular string' => ['hello'];
        yield 'single character' => ['x'];
        yield 'zero as string' => ['0'];
        yield 'string with surrounding spaces' => [' hello '];
    }

    #[DataProvider('provideTestItPassesForNonEmptyStringData')]
    public function testItPassesForNonEmptyString(string $value): void
    {
        Assert::isNonEmptyString($value);
        static::assertTrue(true);
    }

    public static function provideTestItFailsForInvalidNonEmptyStringData(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'null' => [null];
        yield 'integer' => [1];
        yield 'false' => [false];
        yield 'array' => [[]];
    }

    #[DataProvider('provideTestItFailsForInvalidNonEmptyStringData')]
    public function testItFailsForInvalidNonEmptyString(mixed $value): void
    {
        $this->expectException(InvalidRequiredStringException::class);
        Assert::withKeyPrefix('test', fn() => Assert::isNonEmptyString($value, 'field'));
    }

    public function testItIncludesKeyInNonEmptyStringExceptionMessage(): void
    {
        $this->expectException(InvalidRequiredStringException::class);
        $this->expectExceptionMessage("Expected a non-empty string for key 'test.field', got null");
        Assert::withKeyPrefix('test', fn() => Assert::isNonEmptyString(null, 'field'));
    }

    // =========================================================================
    // isNonEmptyStringOrNull
    // =========================================================================

    public static function provideTestItPassesForNonEmptyStringOrNullData(): iterable
    {
        yield 'null' => [null];
        yield 'non-empty string' => ['hello'];
        yield 'zero as string' => ['0'];
    }

    #[DataProvider('provideTestItPassesForNonEmptyStringOrNullData')]
    public function testItPassesForNonEmptyStringOrNull(mixed $value): void
    {
        Assert::isNonEmptyStringOrNull($value);
        static::assertTrue(true);
    }

    public static function provideTestItFailsForInvalidNonEmptyStringOrNullData(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'integer' => [1];
        yield 'false' => [false];
        yield 'resource' => [fopen('php://temp', 'r')];
        yield 'request object' => [new Request()];
        yield 'response object' => [new Response()];
        yield 'array triggering json encoding error' => [[INF]];
    }

    #[DataProvider('provideTestItFailsForInvalidNonEmptyStringOrNullData')]
    public function testItFailsForInvalidNonEmptyStringOrNull(mixed $value): void
    {
        $this->expectException(InvalidRequiredStringException::class);
        Assert::isNonEmptyStringOrNull($value, 'field');
    }

    public function testItIncludesKeyInNonEmptyStringOrNullExceptionMessage(): void
    {
        $this->expectException(InvalidRequiredStringException::class);
        $this->expectExceptionMessage("Expected a non-empty string for key 'field', got Integer(1)");
        Assert::isNonEmptyStringOrNull(1, 'field');
    }

    public function testItDoesNotApplyKeyPrefixToNonEmptyStringOrNull(): void
    {
        // isNonEmptyStringOrNull passes $key directly instead of going through prefixKey(),
        // so withKeyPrefix() has no effect on its key argument.
        try {
            Assert::withKeyPrefix('prefix', fn() => Assert::isNonEmptyStringOrNull('', 'field'));
            static::fail('Expected InvalidRequiredStringException was not thrown');
        } catch (InvalidRequiredStringException $e) {
            static::assertStringContainsString("for key 'field'", $e->getMessage());
            static::assertStringNotContainsString('prefix', $e->getMessage());
        }
    }

    // =========================================================================
    // isValidUrl
    // =========================================================================

    public static function provideTestItPassesForValidUrlData(): iterable
    {
        yield 'https url' => ['https://example.com'];
        yield 'http url with path and query' => ['http://example.com/path?query=1'];
    }

    #[DataProvider('provideTestItPassesForValidUrlData')]
    public function testItPassesForValidUrl(string $value): void
    {
        Assert::isValidUrl($value);
        static::assertTrue(true);
    }

    public static function provideTestItFailsForInvalidUrlData(): iterable
    {
        yield 'plain string' => ['not-a-url'];
        yield 'null' => [null];
        yield 'integer' => [123];
        yield 'empty string' => [''];
        yield 'missing scheme' => ['example.com'];
    }

    #[DataProvider('provideTestItFailsForInvalidUrlData')]
    public function testItFailsForInvalidUrl(mixed $value): void
    {
        $this->expectException(InvalidUriException::class);
        Assert::withKeyPrefix('test', fn() => Assert::isValidUrl($value, 'field'));
    }

    public function testItIncludesKeyInUrlExceptionMessage(): void
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage("for key 'test.field'");
        Assert::withKeyPrefix('test', fn() => Assert::isValidUrl('not-a-url', 'field'));
    }

    public function testInvalidUriExceptionForExceptionOfUriParsing(): void
    {
        // This test is only here, because the location where Assert::isValidUrl() is used in
        // the codebase is currently not testable; it is just defensive programming in case the
        // URI parsing logic changes in the future.
        // So for now, I will just test the exception message formatting logic of InvalidUriException::forExceptionOfUriParsing()
        // by triggering a parsing exception with an obviously invalid URI.
        $previous = new \InvalidArgumentException('URI parsing failed');
        $e = InvalidUriException::forExceptionOfUriParsing($previous, 'key.field');
        static::assertInstanceOf(InvalidUriException::class, $e);
        static::assertSame($previous, $e->getPrevious());
        static::assertStringContainsString("for key 'key.field'", $e->getMessage());
        static::assertStringContainsString('URI parsing failed', $e->getMessage());
    }

    // =========================================================================
    // isIn
    // =========================================================================

    public function testItPassesWhenStringValueIsInList(): void
    {
        Assert::isIn('foo', ['foo', 'bar']);
        static::assertTrue(true);
    }

    public function testItPassesWhenIntegerValueIsInList(): void
    {
        Assert::isIn(2, [1, 2, 3]);
        static::assertTrue(true);
    }

    public function testItFailsWhenValueIsNotInList(): void
    {
        $this->expectException(ValueIsNotInListException::class);
        Assert::withKeyPrefix('test', fn() => Assert::isIn('baz', ['foo', 'bar'], 'field'));
    }

    public function testItFailsWithStrictTypeComparison(): void
    {
        // '1' (string) must not match 1 (int)
        $this->expectException(ValueIsNotInListException::class);
        Assert::withKeyPrefix('test', fn() => Assert::isIn('1', [1, 2, 3], 'field'));
    }

    public function testItIncludesListAndKeyInIsInExceptionMessage(): void
    {
        $this->expectException(ValueIsNotInListException::class);
        $this->expectExceptionMessage('Expected a value from the list ["foo", "bar"] for key \'test.field\', got String("baz")');
        Assert::withKeyPrefix('test', fn() => Assert::isIn('baz', ['foo', 'bar'], 'field'));
    }

    // =========================================================================
    // isArrayOf
    // =========================================================================

    public function testItPassesForArrayOfStrings(): void
    {
        Assert::isArrayOf(['hello', 'world'], 'string');
        static::assertTrue(true);
    }

    public function testItPassesForEmptyArrayOf(): void
    {
        Assert::isArrayOf([], 'string');
        static::assertTrue(true);
    }

    public function testItPassesForArrayOfIntegers(): void
    {
        Assert::isArrayOf([1, 2, 3], 'int');
        static::assertTrue(true);
    }

    public function testItFailsWhenValueIsNotAnArrayOf(): void
    {
        $this->expectException(InvalidArrayOfTypesException::class);
        $this->expectExceptionMessage('Expected an array of string');
        Assert::isArrayOf('not-an-array', 'string', 'field');
    }

    public function testItFailsWhenArrayOfContainsItemWithWrongType(): void
    {
        $this->expectException(InvalidArrayOfTypesException::class);
        $this->expectExceptionMessage("Expected all items in the array to be of type string");
        Assert::isArrayOf(['hello', 42], 'string', 'field');
    }

    public function testItIncludesOffendingIndexInArrayOfExceptionMessage(): void
    {
        $this->expectException(InvalidArrayOfTypesException::class);
        $this->expectExceptionMessage("item at index '2' is of type Integer(99)");
        Assert::isArrayOf(['a', 'b', 99], 'string', 'field');
    }

    // =========================================================================
    // isArrayOfInstances
    // =========================================================================

    public function testItPassesForArrayOfInstances(): void
    {
        Assert::isArrayOfInstances([new SampleObject(), new SampleObject()], SampleObject::class);
        static::assertTrue(true);
    }

    public function testItPassesForEmptyArrayOfInstances(): void
    {
        Assert::isArrayOfInstances([], SampleObject::class);
        static::assertTrue(true);
    }

    public function testItFailsWhenValueIsNotAnArrayOfInstances(): void
    {
        $this->expectException(InvalidArrayOfTypesException::class);
        $this->expectExceptionMessage('Expected an array of ' . SampleObject::class);
        Assert::isArrayOfInstances('not-an-array', SampleObject::class, 'field');
    }

    public function testItFailsWhenArrayContainsInstanceOfWrongClass(): void
    {
        $this->expectException(InvalidArrayOfTypesException::class);
        $this->expectExceptionMessage('Expected all items in the array to be of type ' . SampleObject::class);
        Assert::isArrayOfInstances([new SampleObject(), new \stdClass()], SampleObject::class, 'field');
    }

    public function testItIncludesOffendingIndexInArrayOfInstancesExceptionMessage(): void
    {
        $this->expectException(InvalidArrayOfTypesException::class);
        $this->expectExceptionMessage("item at index '1'");
        Assert::isArrayOfInstances([new SampleObject(), new \stdClass()], SampleObject::class, 'field');
    }

    // =========================================================================
    // is (custom assertion)
    // =========================================================================

    public function testItPassesWhenCustomAssertionCallableReturnsNull(): void
    {
        Assert::is('value', fn($v) => null, 'field');
        static::assertTrue(true);
    }

    public function testItPassesWhenCustomAssertionCallableReturnsNonString(): void
    {
        Assert::is('value', fn($v) => true, 'field');
        static::assertTrue(true);
    }

    public function testItFailsWhenCustomAssertionCallableReturnsString(): void
    {
        $this->expectException(InvalidCustomAssertionException::class);
        $this->expectExceptionMessage('custom error message');
        Assert::withKeyPrefix('test', fn() => Assert::is('bad', fn($v) => 'custom error message', 'field'));
    }

    public function testItFailsWhenCustomAssertionCallableThrows(): void
    {
        $this->expectException(InvalidCustomAssertionException::class);
        $this->expectExceptionMessage('unexpected failure');
        Assert::withKeyPrefix('test', function () {
            Assert::is('bad', function ($v): void {
                throw new \RuntimeException('unexpected failure');
            }, 'field');
        });
    }

    public function testItIncludesValueInCustomAssertionExceptionMessage(): void
    {
        $this->expectException(InvalidCustomAssertionException::class);
        $this->expectExceptionMessage('String("myvalue")');
        Assert::withKeyPrefix('test', fn() => Assert::is('myvalue', fn($v) => 'invalid', 'field'));
    }

    public function testItIncludesKeyInCustomAssertionExceptionMessage(): void
    {
        $this->expectException(InvalidCustomAssertionException::class);
        $this->expectExceptionMessage("for key 'test.field'");
        Assert::withKeyPrefix('test', fn() => Assert::is('bad', fn($v) => 'fail', 'field'));
    }

    // =========================================================================
    // withKeyPrefix
    // =========================================================================

    public function testItAppliesKeyPrefixToAssertions(): void
    {
        $this->expectException(InvalidRequiredPositiveIntegerException::class);
        $this->expectExceptionMessage("test.count");
        Assert::withKeyPrefix('test', fn() => Assert::isPositiveInteger(0, 'count'));
    }

    public function testItNestsKeyPrefixes(): void
    {
        $this->expectException(InvalidRequiredPositiveIntegerException::class);
        $this->expectExceptionMessage("config.db.port");
        Assert::withKeyPrefix('config', function () {
            Assert::withKeyPrefix('db', fn() => Assert::isPositiveInteger(0, 'port'));
        });
    }

    public function testItRestoresPrefixAfterCallableCompletes(): void
    {
        Assert::withKeyPrefix('outer', fn() => null);

        try {
            Assert::isPositiveInteger(0, 'standalone');
            static::fail('Expected InvalidRequiredPositiveIntegerException was not thrown');
        } catch (InvalidRequiredPositiveIntegerException $e) {
            static::assertStringNotContainsString('outer', $e->getMessage());
        }
    }

    public function testItRestoresPrefixAfterExceptionInCallable(): void
    {
        try {
            Assert::withKeyPrefix('outer', fn() => Assert::isPositiveInteger(0, 'field'));
        } catch (InvalidRequiredPositiveIntegerException) {
            // Expected; prefix must be restored by the finally block
        }

        try {
            Assert::isPositiveInteger(0, 'standalone');
            static::fail('Expected InvalidRequiredPositiveIntegerException was not thrown');
        } catch (InvalidRequiredPositiveIntegerException $e) {
            static::assertStringNotContainsString('outer', $e->getMessage());
        }
    }

    public function testItRunsMultipleCallablesInOrder(): void
    {
        $log = [];
        Assert::withKeyPrefix(
            'test',
            function () use (&$log) {
                $log[] = 'first';
            },
            function () use (&$log) {
                $log[] = 'second';
            }
        );
        static::assertSame(['first', 'second'], $log);
    }
}
