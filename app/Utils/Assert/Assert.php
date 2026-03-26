<?php
declare(strict_types=1);


namespace App\Utils\Assert;


use App\Utils\Assert\Exception\InvalidArrayOfTypesException;
use App\Utils\Assert\Exception\InvalidCustomAssertionException;
use App\Utils\Assert\Exception\InvalidRequiredNonNegativeIntegerException;
use App\Utils\Assert\Exception\InvalidRequiredPositiveIntegerException;
use App\Utils\Assert\Exception\InvalidRequiredStringException;
use App\Utils\Assert\Exception\InvalidUriException;
use App\Utils\Assert\Exception\ValueIsNotInListException;
use GuzzleHttp\Psr7\Uri;

/**
 * A super simple assertion utility class that throws exceptions with detailed messages when assertions fail.
 *
 * All methods are static and throw typed exceptions on failure, allowing callers to catch specific
 * assertion errors. An optional key prefix can be applied via {@see Assert::withKeyPrefix()} to
 * namespace keys in nested validation contexts.
 *
 * The `@psalm-assert` annotations on each method enable Psalm (and compatible tools like PHPStan
 * and PhpStorm) to narrow the type of `$value` in the calling scope after a successful assertion,
 * eliminating the need for redundant type checks downstream.
 */
class Assert
{
    /**
     * The currently active key prefix, applied to all key arguments in nested assertion calls.
     * Set and restored by {@see Assert::withKeyPrefix()}.
     */
    private static ?string $currentKeyPrefix = null;


    /**
     * Asserts that the given value is a positive integer (> 0).
     *
     * After this call, static analysers will narrow `$value` to `int<1, max>`.
     *
     * @param mixed $value The value to check.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert int<1, max> $value
     *
     * @throws InvalidRequiredPositiveIntegerException if the value is not a positive integer.
     */
    public static function isPositiveInteger(mixed $value, ?string $key = null): void
    {
        if (!is_int($value) || $value <= 0) {
            throw InvalidRequiredPositiveIntegerException::forInvalidValue($value, self::prefixKey($key));
        }
    }

    /**
     * Asserts that the given value is a non-negative integer (>= 0).
     *
     * After this call, static analysers will narrow `$value` to `int<0, max>`.
     *
     * @param mixed $value The value to check.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert int<0, max> $value
     *
     * @throws InvalidRequiredNonNegativeIntegerException if the value is not a non-negative integer.
     */
    public static function isNonNegativeInteger(mixed $value, ?string $key = null): void
    {
        if (!is_int($value) || $value < 0) {
            throw InvalidRequiredNonNegativeIntegerException::forInvalidValue($value, self::prefixKey($key));
        }
    }

    /**
     * Asserts that the given value is a non-empty string (not empty after trimming whitespace).
     *
     * After this call, static analysers will narrow `$value` to `non-empty-string`.
     *
     * @param mixed $value The value to check.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert non-empty-string $value
     *
     * @throws InvalidRequiredStringException if the value is not a non-empty string.
     */
    public static function isNonEmptyString(mixed $value, ?string $key = null): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw InvalidRequiredStringException::forInvalidValue($value, self::prefixKey($key));
        }
    }

    /**
     * Asserts that the given value is either null or a non-empty string (not empty after trimming whitespace).
     *
     * Unlike {@see Assert::isNonEmptyString()}, this method permits null as a valid value,
     * making it suitable for optional fields that, when provided, must not be blank.
     *
     * After this call, static analysers will narrow `$value` to `non-empty-string|null`.
     *
     * @param mixed $value The value to check.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert non-empty-string|null $value
     *
     * @throws InvalidRequiredStringException if the value is not null and not a non-empty string.
     */
    public static function isNonEmptyStringOrNull(mixed $value, ?string $key = null): void
    {
        if ($value !== null && (!is_string($value) || trim($value) === '')) {
            throw InvalidRequiredStringException::forInvalidValue($value, $key);
        }
    }

    /**
     * Asserts that the given value is a syntactically valid URL that can also be parsed by the PSR-7 Uri implementation.
     *
     * After this call, static analysers will narrow `$value` to `non-empty-string`.
     *
     * @param mixed $value The value to check.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert non-empty-string $value
     *
     * @throws InvalidUriException if the value is not a valid URL or cannot be parsed.
     */
    public static function isValidUrl(mixed $value, ?string $key = null): void
    {
        /** @noinspection BypassedUrlValidationInspection */
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw InvalidUriException::forInvalidValue($value, self::prefixKey($key));
        }
        try {
            new Uri($value);
        } catch (\InvalidArgumentException $e) {
            throw InvalidUriException::forExceptionOfUriParsing($e, self::prefixKey($key));
        }
    }

    /**
     * Asserts that the given value is strictly contained in the list of allowed values.
     *
     * Uses strict comparison (===), so type must also match.
     *
     * The template parameter `T` is inferred from `$allowedValues`, so after this call
     * static analysers will narrow `$value` to the union type of all values in the list.
     *
     * Example:
     * ```php
     * // $value is narrowed to 'foo'|'bar' after this call
     * Assert::isIn($value, ['foo', 'bar']);
     * ```
     *
     * @template T
     *
     * @param mixed $value The value to check.
     * @param T[] $allowedValues The list of acceptable values.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert T $value
     *
     * @throws ValueIsNotInListException if the value is not in the allowed list.
     */
    public static function isIn(mixed $value, array $allowedValues, ?string $key = null): void
    {
        if (!in_array($value, $allowedValues, true)) {
            throw ValueIsNotInListException::forInvalidValue($value, $allowedValues, self::prefixKey($key));
        }
    }

    /**
     * Asserts that the given value is an array where every element is of the specified class or type.
     *
     * The type is matched using {@see get_debug_type()}, which supports native types (e.g. "int", "string")
     * as well as fully qualified class names (e.g. "App\Models\User").
     *
     * The template parameter `T` is inferred from the `$type` string, so after this call
     * static analysers will narrow `$value` to `array<T>`.
     *
     * Example:
     * ```php
     * // $value is narrowed to array<\App\Models\User> after this call
     * Assert::isArrayOf($value, \App\Models\User::class);
     * ```
     *
     * @template T of object
     *
     * @param mixed $value The value to check; must be an array.
     * @param class-string<T> $type The expected class of each array item.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert array<T> $value
     *
     * @throws InvalidArrayOfTypesException if the value is not an array,
     *         or if any element does not match the expected type.
     */
    public static function isArrayOf(mixed $value, string $type, ?string $key = null): void
    {
        if (!is_array($value)) {
            throw InvalidArrayOfTypesException::forNonArrayValue($value, $type, $key);
        }
        foreach ($value as $index => $item) {
            if (get_debug_type($item) !== $type) {
                throw InvalidArrayOfTypesException::forInvalidItem($value, $index, $type, $key);
            }
        }
    }

    /**
     * Asserts a value using a custom callable assertion.
     *
     * The callable receives the value and may signal failure in two ways:
     * - By returning a non-empty string, which is used as the failure message.
     * - By throwing any {@see \Throwable}, which is wrapped in the assertion exception.
     *
     * If the callable returns anything other than a string (e.g. void/null/true), the assertion is considered passed.
     *
     * The template parameter `T` allows callers to declare what type `$value` should be narrowed
     * to after a successful assertion by typing the `$assertion` callable accordingly:
     *
     * Example:
     * ```php
     * // $value is narrowed to \App\Models\User after this call
     * Assert::is($value, /** @param mixed $v @psalm-assert \App\Models\User $v * / function(mixed $v): void {
     *     if (!$v instanceof \App\Models\User) throw new \InvalidArgumentException('Not a User');
     * });
     * ```
     *
     * @template T
     *
     * @param mixed $value The value to assert.
     * @param callable(T): void $assertion A callable that receives the value and signals failure by returning a string or throwing.
     *                                        Annotate the callable's parameter with `@psalm-assert` to propagate narrowing.
     * @param string|null $key Optional key name used in the exception message for context.
     *
     * @psalm-assert T $value
     *
     * @throws InvalidCustomAssertionException if the assertion callable returns a string message or throws.
     */
    public static function is(mixed $value, callable $assertion, ?string $key = null): void
    {
        try {
            $res = $assertion($value);
            if (is_string($res)) {
                throw InvalidCustomAssertionException::fromCustomMessage($res, $value, self::prefixKey($key));
            }
        } catch (\Throwable $e) {
            throw InvalidCustomAssertionException::fromThrownException($e, $value, self::prefixKey($key));
        }
    }

    /**
     * Executes one or more assertion callables under a shared key prefix.
     *
     * All calls to assertion methods within the provided callables will have their key arguments
     * automatically prefixed with the given prefix (dot-separated). Prefixes can be nested by
     * calling this method recursively.
     *
     * Example:
     * ```php
     * Assert::withKeyPrefix('config', function() use ($config) {
     *     Assert::isNonEmptyString($config['name'], 'name'); // key: "config.name"
     *     Assert::withKeyPrefix('db', function() use ($config) {
     *         Assert::isPositiveInteger($config['db']['port'], 'port'); // key: "config.db.port"
     *     });
     * });
     * ```
     *
     * @param string $keyPrefix The prefix to prepend to all key arguments within the assertions.
     * @param callable ...$assertions One or more zero-argument callables containing assertion calls.
     */
    public static function withKeyPrefix(
        string   $keyPrefix,
        callable ...$assertions
    ): void
    {
        $previousKeyPrefix = self::$currentKeyPrefix;
        self::$currentKeyPrefix = self::prefixKey($keyPrefix);
        try {
            foreach ($assertions as $assertion) {
                $assertion();
            }
        } finally {
            self::$currentKeyPrefix = $previousKeyPrefix;
        }
    }

    /**
     * Builds a fully-prefixed key string by combining the current key prefix with the given key.
     *
     * - If both a prefix and a key are present, returns `"<prefix>.<key>"`.
     * - If only a prefix is set and key is null, returns the prefix alone.
     * - If no prefix is set and key is null, returns null (cast to empty string by return type).
     *
     * @param string|null $key The key to prefix, or null if no key was provided.
     *
     * @return string  The prefixed key, or just the current prefix (possibly null cast to empty string).
     */
    private static function prefixKey(?string $key): string
    {
        return $key !== null ? self::$currentKeyPrefix . '.' . $key : self::$currentKeyPrefix;
    }
}
