<?php
declare(strict_types=1);


namespace App\Utils\Lists\Exceptions;


/**
 * Thrown when the key generator closure passed to {@see \App\Utils\Lists\LazySingletonList}
 * returns a value that is not a string.
 */
class InvalidKeyGeneratorResultException extends \InvalidArgumentException
{
    public static function forNonStringResult(): self
    {
        return new self('Key generator must return a string.');
    }
}
