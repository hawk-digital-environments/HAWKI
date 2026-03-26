<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class AbstractAssertionException extends \InvalidArgumentException implements AssertExceptionInterface
{
    /**
     * A helper method to generate a string for the optional key part of an error message.
     * If the key is provided, it returns " for key 'key'", otherwise it returns an empty string.
     * @param string|null $key
     * @return string
     */
    public static function optionalKey(?string $key): string
    {
        return $key !== null ? " for key '$key'" : '';
    }

    /**
     * A helper method to convert various types of values into a readable string format for error messages.
     * Similar to {@see get_debug_type()} but with more detail for certain types and a more concise output for strings and arrays.
     * @param mixed $value
     * @return string
     */
    protected static function valueToString(mixed $value): string
    {
        if (is_object($value)) {
            if ($value instanceof Request) {
                return 'Request';
            }
            if ($value instanceof Response) {
                return 'Response';
            }
            return 'Object(' . $value::class . ')';
        }
        if (is_array($value)) {
            try {
                return 'Array(' . json_encode($value, JSON_THROW_ON_ERROR) . ')';
            } catch (\JsonException) {
            }
            return 'Array(' . count($value) . ')';
        }
        if (is_resource($value)) {
            return 'Resource(' . get_resource_type($value) . ')';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            $shortened = strlen($trimmed) > 20 ? substr($trimmed, 0, 17) . '...' : $trimmed;
            return 'String("' . $shortened . '")';
        }
        if (is_int($value)) {
            return 'Integer(' . $value . ')';
        }
        if (is_float($value)) {
            return 'Float(' . $value . ')';
        }
        return get_debug_type($value);
    }
}
