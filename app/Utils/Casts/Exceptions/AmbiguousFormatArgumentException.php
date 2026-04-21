<?php

declare(strict_types=1);

namespace App\Utils\Casts\Exceptions;

/**
 * @api
 */
class AmbiguousFormatArgumentException extends \InvalidArgumentException implements CastableObjectExceptionInterface
{
    public static function forDuplicateFormat(string $typeString, string $formatArg): self
    {
        return new self(\sprintf(
            'Format/argument string was provided both inside the type string ("%s") and as the second constructor argument ("%s"). Use one or the other, not both.',
            $typeString,
            $formatArg,
        ));
    }
}
