<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;


class InvalidUriException extends AbstractAssertionException
{
    public function __construct(
        mixed   $value,
        ?string $key = null,
    )
    {
        $keyPart = $key !== null ? " for key '$key'" : '';
        parent::__construct("Expected a valid URI$keyPart, got: " . (is_string($value) ? $value : gettype($value)));
    }
    
}
