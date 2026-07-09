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
    /**
     * Used when the `file_converter.default` or `file_converter.fallback` config value
     * names a type that has no entry under `file_converter.converters`.
     */
    public static function forInvalidConverterType(string $type): self
    {
        return new self(sprintf(
            'Invalid file converter type: "%s". There is no converter configured for this type.',
            $type
        ));
    }

    /**
     * Used when a converter entry exists under `file_converter.converters` but is missing
     * the required `class` key that points to the implementation class.
     */
    public static function forMissingClassInConfig(string $type): self
    {
        return new self(sprintf(
            'Invalid file converter configuration for type: %s. Missing "class" key in configuration.',
            $type
        ));
    }

    /**
     * Used when the `class` key in a converter config points to a class that either does
     * not exist or does not implement {@see FileConverterInterface}.
     */
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
