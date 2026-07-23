<?php
declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Values;

/**
 * Defines the lifecycle point at which the frontend JS migration runner executes a migration.
 *
 * - `AFTER_LOGIN` — runs as soon as the user successfully logs in. Use this when the migration
 *   only needs the user's session, not access to encrypted passkey-protected data.
 * - `AFTER_PASSKEY` — runs after the user has entered (and verified) their passkey, giving the
 *   JS migration access to locally-decrypted data. Required when the migration must read or
 *   transform data that is encrypted with the user's passkey.
 */
enum FrontendMigrationRunType: string
{
    case AFTER_LOGIN = 'after_login';
    case AFTER_PASSKEY = 'after_passkey';
}
