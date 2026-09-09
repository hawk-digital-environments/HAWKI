<?php
declare(strict_types=1);


namespace App\Services\Profile\Repositories;


use App\Models\PasskeyBackup;
use App\Services\Profile\Values\PasskeyBackupSecret;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

/**
 * Database access for `passkey_backups` — exactly one row per username.
 *
 * @extends AbstractRepository<PasskeyBackup>
 */
#[UseModel(PasskeyBackup::class)]
class PasskeyBackupRepository extends AbstractRepository
{
    public function findOneByUsername(string $username): ?PasskeyBackup
    {
        return $this->getQuery()->where('username', $username)->first();
    }

    public function deleteForUsername(string $username): void
    {
        $this->getQuery()->where('username', $username)->delete();
    }

    /**
     * Creates the backup of `$username` or replaces the stored one.
     *
     * Matching on `username` alone is the whole point: the previous implementation passed the
     * ciphertext into the match condition, which made every write insert a new row.
     */
    public function upsert(string $username, PasskeyBackupSecret $secret): PasskeyBackup
    {
        return $this->getQuery()->updateOrCreate(
            ['username' => $username],
            [
                'ciphertext' => $secret->ciphertext,
                'iv' => $secret->iv,
                'tag' => $secret->tag,
            ]
        );
    }
}
