<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Exceptions;


/**
 * Thrown when a `down()` method is called on a frontend migration.
 *
 * Frontend migrations operate on client-side data that is encrypted with the user's passkey.
 * The server never has access to the plaintext, so it cannot reverse the transformation.
 * Additionally, once a client applies a migration, the old format is gone — reverting the
 * server-side record would leave that user's data inconsistent with everyone who has not
 * migrated yet. For these reasons, frontend migrations are intentionally one-way only.
 *
 * If you need to undo a change, write a new forward migration that transforms back to the
 * previous format instead of rolling back the original migration.
 */
class NoDownForFrontendMigrationsExceptionException extends \RuntimeException implements FrontendMigrationExceptionInterface
{
    /**
     * Creates the exception for a specific migration that attempted a rollback.
     *
     * @param string $migrationName The migration class name that called `down()`.
     */
    public static function forMigration(string $migrationName): self
    {
        return new self(sprintf(
            'The migration "%s" is migrating data, only accessible with the user\'s passkey. Therefore we can not provide a down migration for it. This migration is one way only. Sorry :(',
            $migrationName
        ));
    }
}
