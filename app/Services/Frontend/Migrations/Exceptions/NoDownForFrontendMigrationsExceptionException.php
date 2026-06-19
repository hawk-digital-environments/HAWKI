<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Exceptions;


class NoDownForFrontendMigrationsExceptionException extends \RuntimeException implements FrontendMigrationExceptionInterface
{
    public static function forMigration(string $migrationName): self
    {
        return new self(sprintf(
            'The migration "%s" is migrating data, only accessible with the user\'s passkey. Therefore we can not provide a down migration for it. This migration is one way only. Sorry :(',
            $migrationName
        ));
    }
}
