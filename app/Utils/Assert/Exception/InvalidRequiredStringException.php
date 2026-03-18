<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;


class InvalidRequiredStringException extends AbstractAssertionException
{
    public function __construct(
        mixed   $value,
        ?string $key = null,
    )
    {
        $keyPart = $key !== null ? " for key '$key'" : '';
        $valueString = gettype($value);
        if ($valueString === 'string' && $value !== '') {
            $valueString = ': "' . $value . '"';
        } else if ($valueString === 'string' && $value === '') {
            $valueString = ' an empty string';
        }
        parent::__construct("Expected a non-empty string$keyPart, got" . $valueString);
    }
}
