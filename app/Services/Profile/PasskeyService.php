<?php
declare(strict_types=1);

namespace App\Services\Profile;

use App\Services\Profile\Repositories\PasskeyBackupRepository;
use App\Services\Profile\Values\PasskeyBackupSecret;
use Illuminate\Container\Attributes\Singleton;

/**
 * @api
 *
 * Reads and writes the encrypted passkey backup of a user.
 *
 * The actor is always passed in explicitly. The previous version resolved it from
 * `Session::get('authenticatedUserInfo')` / `Auth::user()`, which made the service unusable
 * during registration (no authenticated user yet) and carried a comparison of the username with
 * itself that could never fail — so the intended "is this really your backup?" check did not
 * exist. Callers now name the owner, and the HTTP layer decides who that is.
 *
 * The service never sees the passkey or the recovery code, only the
 * {@see PasskeyBackupSecret} the browser produced.
 */
#[Singleton]
readonly class PasskeyService
{
    public function __construct(
        private PasskeyBackupRepository $repository
    )
    {
    }

    /**
     * Stores (or replaces) the passkey backup of `$username`.
     */
    public function backupPassKey(string $username, PasskeyBackupSecret $secret): void
    {
        $this->repository->upsert($username, $secret);
    }

    /**
     * Returns the stored passkey backup of `$username`, or null when the user has none.
     */
    public function retrievePasskeyBackup(string $username): ?PasskeyBackupSecret
    {
        $backup = $this->repository->findOneByUsername($username);

        if ($backup === null) {
            return null;
        }

        return new PasskeyBackupSecret(
            ciphertext: (string)$backup->ciphertext,
            iv: (string)$backup->iv,
            tag: (string)$backup->tag
        );
    }
}
