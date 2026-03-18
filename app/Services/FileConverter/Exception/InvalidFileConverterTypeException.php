<?php
declare(strict_types=1);


namespace App\Services\FileConverter\Exception;

/**
 * Triggered when the system tries to build the file converter instance,
 * but the configured instance type is not valid (e.g. no converter configured for that type).
 */
class InvalidFileConverterTypeException extends \InvalidArgumentException implements FileConverterExceptionInterface
{
    public function __construct(string $type)
    {
        parent::__construct("Invalid file converter type: '$type'. There is no converter configured for this type.");
    }
}
