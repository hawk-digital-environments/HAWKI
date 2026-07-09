<?php
declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Exceptions;

/**
 * Thrown when a migration's user-data finder closure returns an unexpected type.
 *
 * The finder must return either an `array` (the data to store) or an empty/falsy value
 * (`null`, `false`, `[]`) to indicate that no data needs to be stored for that user.
 * Any other return type (e.g. a string or an object) triggers this exception.
 */
class InvalidUserDataFinderResultException extends \RuntimeException implements FrontendMigrationExceptionInterface
{
    /**
     * Creates the exception for a migration whose finder returned a non-array, non-empty value.
     *
     * @param string $migrationName The migration filename (without extension) that produced the bad return value.
     */
    public static function forNonArrayReturnType(string $migrationName): self
    {
        return new self(sprintf(
            'User data finder closure for migration "%s" must return an array or null/false.',
            $migrationName
        ));
    }
}
