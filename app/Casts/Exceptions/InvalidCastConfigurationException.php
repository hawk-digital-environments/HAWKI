<?php
declare(strict_types=1);

namespace App\Casts\Exceptions;

use App\Casts\AsInstance;
use App\Casts\Contracts\CastableInstanceInterface;

/**
 * Thrown when {@see AsInstance} is configured incorrectly, e.g. a missing or invalid class argument.
 */
class InvalidCastConfigurationException extends \InvalidArgumentException implements CastExceptionInterface
{
    public static function forMissingClassArgument(): self
    {
        return new self(sprintf(
            '%s can only be used with a class name argument, e.g. %s:App\\Services\\AI\\Values\\ModelIoList',
            AsInstance::class,
            AsInstance::class
        ));
    }

    public static function forUnknownClass(string $className): self
    {
        return new self(sprintf(
            'Class [%s] does not exist for %s cast.',
            $className,
            AsInstance::class
        ));
    }

    public static function forMissingInterface(string $className): self
    {
        return new self(sprintf(
            'Class [%s] must implement %s to be used with %s cast.',
            $className,
            CastableInstanceInterface::class,
            AsInstance::class
        ));
    }
}
