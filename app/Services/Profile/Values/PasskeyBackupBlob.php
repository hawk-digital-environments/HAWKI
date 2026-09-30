<?php
declare(strict_types=1);


namespace App\Services\Profile\Values;


/**
 * A stored passkey backup as delivered through the `passkey-backups` JSON:API resource.
 *
 * The resource id is always the literal `me`: a backup is only ever readable by its own owner,
 * so exposing the database id would add nothing but a way to probe for other users' backups.
 * Access is enforced in `App\JsonApi\V1\PasskeyBackups\PasskeyBackupRepository`, which resolves
 * the blob from the current user only — the schema is not `authorizable()`.
 */
readonly class PasskeyBackupBlob
{
    /** The only valid resource id for this resource type. */
    public const string OWN_ID = 'me';

    public function __construct(
        public string              $id,
        public PasskeyBackupSecret $secret
    )
    {
    }

    public static function forOwner(PasskeyBackupSecret $secret): self
    {
        return new self(self::OWN_ID, $secret);
    }
}
