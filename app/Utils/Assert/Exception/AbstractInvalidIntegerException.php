<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;

abstract class AbstractInvalidIntegerException extends AbstractAssertionException
{
    public function __construct(
        mixed   $value,
        string  $expectedMessageString,
        ?string $key = null,
    )
    {
        $keyPart = $key !== null ? " for key '$key'" : '';
        $valueString = gettype($value);
        if ($valueString === 'integer' || $valueString === 'double') {
            $valueString = ': ' . $value;
        } else if ($valueString === 'string' && $value !== '') {
            $valueString = ': "' . $value . '"';
        } else if ($valueString === 'string' && $value === '') {
            $valueString = ' an empty string';
        }
        parent::__construct("Expected $expectedMessageString$keyPart, got" . $valueString);
    }
}
