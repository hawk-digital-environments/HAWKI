<?php
declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Exceptions;

class InvalidUserDataFinderResultException extends \RuntimeException implements FrontendMigrationExceptionInterface
{
    public static function forNonArrayReturnType(string $migrationName): self
    {
        return new self(sprintf(
            'User data finder closure for migration "%s" must return an array or null/false.',
            $migrationName
        ));
    }
}
