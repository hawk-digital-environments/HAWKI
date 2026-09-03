<?php

declare(strict_types=1);

namespace App\Services\System\Http\Exceptions;

/**
 * Thrown when a validated request value cannot be coerced to the type declared on the
 * target property of a config/settings object — e.g. an object property type the
 * mapper has no coercion rule for.
 *
 * This is a programming error: the property's `#[ValidateInput]` rule and its declared
 * type do not fit together, or the property type needs a coercion the mapper does not
 * implement yet.
 */
class UnmappablePropertyValueException extends \LogicException implements HttpExceptionInterface
{
    /**
     * Creates the exception for a property whose declared type the mapper cannot
     * produce from a validated request value.
     */
    public static function forProperty(string $objectClass, string $property, string $declaredType): self
    {
        return new self(\sprintf(
            'Cannot map the validated input for property "%s" of "%s": the declared type "%s"'
            . ' has no input coercion. Extend the coercion rules of the request-to-object mapper,'
            . ' or change the property type to a scalar, array or enum.',
            $property,
            $objectClass,
            $declaredType,
        ));
    }
}
