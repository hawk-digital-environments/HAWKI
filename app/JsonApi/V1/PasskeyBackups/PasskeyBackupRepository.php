<?php

namespace App\JsonApi\V1\PasskeyBackups;

use App\Http\Errors\CodedError;
use App\Models\User;
use App\Services\Profile\PasskeyService;
use App\Services\Profile\Values\PasskeyBackupBlob;
use Illuminate\Container\Attributes\CurrentUser;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class PasskeyBackupRepository extends AbstractRepository
{
    public function __construct(
        private readonly PasskeyService $passkeyService,
        #[CurrentUser]
        private readonly User           $user
    )
    {
    }

    /**
     * Resolves the current user's own backup.
     *
     * `me` is the only id that resolves to anything. Any other id gets a plain "not found" with
     * no error code: whether some other user has a backup is not information this endpoint
     * should reveal, not even by answering differently for ids that exist.
     *
     * A missing backup for `me`, on the other hand, is a state the client has to act on (offer
     * to create one), so it gets the `passkey_backup_not_found` code.
     */
    public function find(string $resourceId): ?object
    {
        if ($resourceId !== PasskeyBackupBlob::OWN_ID) {
            return null;
        }

        $secret = $this->passkeyService->retrievePasskeyBackup($this->user->username);

        if ($secret === null) {
            CodedError::abort(
                'passkey_backup_not_found',
                404,
                'Passkey backup not found',
                'There is no passkey backup stored for the current user.'
            );
        }

        return PasskeyBackupBlob::forOwner($secret);
    }
}
