<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;


use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvalidCustomAssertionException extends AbstractAssertionException
{
    public function __construct(
        public readonly mixed $value,
        string                $message,
        ?string               $key = null,
        ?\Throwable           $previous = null
    )
    {
        $valueString = $this->valueToString($value);
        $fullMessage = 'Invalid value ' . $valueString . ($key !== null ? ' for key "' . $key . '"' : '') . ': ' . $message;
        parent::__construct($fullMessage, 0, $previous);
    }
    
    private function valueToString(mixed $value): string
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
        return gettype($value);
    }
    
}
