<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Exception;

use App\Services\FileConverter\Interfaces\FileConverterInterface;

/**
 * Triggered when the system tries to build the file converter instance,
 * but the configured instance type is not valid (e.g. no converter configured for that type).
 */
class InvalidFileConverterConfigException extends \InvalidArgumentException implements FileConverterExceptionInterface
{
    public static function forInvalidConverterType(string $type): self
    {
        return new self(sprintf(
            'Invalid file converter type: "%s". There is no converter configured for this type.',
            $type
        ));
    }

    public static function forMissingClassInConfig(string $type): self
    {
        return new self(sprintf(
            'Invalid file converter configuration for type: %s. Missing "class" key in configuration.',
            $type
        ));
    }

    public static function forInvalidClassInConfig(string $type, string $class): self
    {
        return new self(sprintf(
            'Invalid file converter class "%s"" for type "%s". The class does not exist or does not implement %s.',
            $class,
            $type,
            FileConverterInterface::class
        ));
    }
}
