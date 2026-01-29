<?php
declare(strict_types=1);


namespace App\Utils\Assert;


use App\Utils\Assert\Exception\InvalidRequiredNonNegativeIntegerException;
use App\Utils\Assert\Exception\InvalidRequiredPositiveIntegerException;
use App\Utils\Assert\Exception\InvalidRequiredStringException;
use App\Utils\Assert\Exception\InvalidUriException;
use App\Utils\Assert\Exception\ValueIsNotInListException;
use GuzzleHttp\Psr7\Uri;

class Assert
{
    private static ?string $currentKeyPrefix = null;
    
    public static function isPositiveInteger(mixed $value, ?string $key = null): void
    {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidRequiredPositiveIntegerException($value, self::prefixKey($key));
        }
    }
    
    public static function isNonNegativeInteger(mixed $value, ?string $key = null): void
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidRequiredNonNegativeIntegerException($value, self::prefixKey($key));
        }
    }
    
    public static function isNonEmptyString(mixed $value, ?string $key = null): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidRequiredStringException($value, self::prefixKey($key));
        }
    }
    
    public static function isValidUrl(mixed $value, ?string $key = null): void
    {
        /** @noinspection BypassedUrlValidationInspection */
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidUriException($value, self::prefixKey($key));
        }
        try {
            new Uri($value);
        } catch (\InvalidArgumentException) {
            throw new InvalidUriException($value, self::prefixKey($key));
        }
    }
    
    public static function isIn(mixed $value, array $allowedValues, ?string $key = null): void
    {
        if (!in_array($value, $allowedValues, true)) {
            throw new ValueIsNotInListException($value, $allowedValues, self::prefixKey($key));
        }
    }
    
    public static function is(mixed $value, callable $assertion, ?string $key = null): void
    {
        try {
            $res = $assertion($value);
            if (is_string($res)) {
                throw new \RuntimeException($res);
            }
        } catch (\Throwable $e) {
            $prefix = self::prefixKey($key);
            $message = $e->getMessage();
            if ($prefix !== null && $prefix !== '') {
                $message = "[$prefix] $message";
            }
            throw new \RuntimeException($message, 0, $e);
        }
    }
    
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
    
    private static function prefixKey(?string $key): string
    {
        return $key !== null ? self::$currentKeyPrefix . '.' . $key : self::$currentKeyPrefix;
    }
}
