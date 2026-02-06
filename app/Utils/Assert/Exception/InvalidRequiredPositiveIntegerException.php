<?php
declare(strict_types=1);


namespace App\Utils\Assert\Exception;

class InvalidRequiredPositiveIntegerException extends AbstractInvalidIntegerException
{
    public function __construct(
        mixed   $value,
        ?string $key = null,
    )
    {
        parent::__construct($value, 'a positive integer', $key);
    }
}
