<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;


class ValueIsNotInListException extends AbstractAssertionException
{
    public function __construct(
        mixed   $value,
        array   $list,
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
        $listString = implode(', ', array_map(fn($item) => is_string($item) ? "\"$item\"" : (string)$item, $list));
        parent::__construct("Expected a value from the list [$listString]$keyPart, got" . $valueString);
    }
}
