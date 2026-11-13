<?php
declare(strict_types=1);


namespace App\Services\Profile\Repositories;


use App\Models\PasskeyBackupArchive;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use DateTimeInterface;

/**
 * Database access for `passkey_backup_archives`, the recovery bin for passkey backups that were
 * removed when `passkey_backups.username` became unique.
 *
 * @extends AbstractRepository<PasskeyBackupArchive>
 */
#[UseModel(PasskeyBackupArchive::class)]
class PasskeyBackupArchiveRepository extends AbstractRepository
{
    /**
     * Whether the archive table exists.
     *
     * The scheduled cleanup runs on every installation, including ones whose migrations have not
     * been run yet (or that rolled the archive migration back), so the caller has to be able to
     * ask instead of failing with a "table not found" error every night.
     */
    public function hasArchiveTable(): bool
    {
        $model = $this->getEloquentInstance();

        return $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
    }

    /**
     * Deletes all archived backups whose retention has run out.
     *
     * @return int The number of deleted rows.
     */
    public function deleteExpired(DateTimeInterface $now): int
    {
        return $this->getQuery()->where('expires_at', '<=', $now)->delete();
    }

    public function countAll(): int
    {
        return $this->getQuery()->count();
    }
}
