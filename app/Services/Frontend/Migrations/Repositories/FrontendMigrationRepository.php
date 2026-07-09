<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Repositories;


use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\User;
use App\Services\Frontend\Migrations\Values\MigrationToApply;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Crypt;

/**
 * Persists and queries the registry of known frontend migrations in `frontend_migrations`.
 *
 * Each row represents a migration that has been registered via `FrontendMigrationBuilder::register()`.
 * The `has_userdata` flag indicates whether per-user context data was collected at registration time.
 */
#[UseModel(FrontendMigration::class)]
class FrontendMigrationRepository extends AbstractRepository
{
    /**
     * Inserts a new frontend migration record.
     *
     * @param string $migrationName Filename-based migration name (without extension).
     * @param bool   $hasUserdata   Whether per-user context data was stored for this migration.
     */
    public function insert(
        string $migrationName,
        bool   $hasUserdata
    ): FrontendMigration
    {
        return $this->getQuery()->create([
            'migration_name' => $migrationName,
            'has_userdata' => $hasUserdata,
        ]);
    }

    /**
     * Returns all migrations whose IDs are not in `$migrationIdsToExclude`.
     * Used to find migrations that have not yet been applied for a given user.
     *
     * @param  int[]                           $migrationIdsToExclude
     * @return Collection<int, FrontendMigration>
     */
    public function findAllWithout(array $migrationIdsToExclude): Collection
    {
        return $this->getQuery()->whereNotIn('id', $migrationIdsToExclude)->get();
    }

    public function findOneByName(string $migrationName): ?FrontendMigration
    {
        return $this->getQuery()->where('migration_name', $migrationName)->first();
    }

    public function drop(FrontendMigration $migration): void
    {
        $migration->delete();
    }

    /**
     * Returns all migrations that `$user` has not yet applied, each decorated with the
     * encrypted per-user context data (if any) that the JS migration will receive.
     *
     * Performs a LEFT JOIN against `applied_frontend_migrations` to exclude already-applied
     * rows, and a LEFT JOIN against `frontend_migration_userdata` to attach context data.
     * Context data is decrypted inline so callers receive plain arrays.
     */
    public function findAllMigrationsToApplyForUser(User $user): SupportCollection
    {
        return $this->getQuery()
            ->leftJoin('applied_frontend_migrations', function ($join) use ($user) {
                $join->on('frontend_migrations.id', '=', 'applied_frontend_migrations.migration_id')
                    ->where('applied_frontend_migrations.user_id', '=', $user->id);
            })
            ->leftJoin('frontend_migration_userdata', function ($join) use ($user) {
                $join->on('frontend_migrations.id', '=', 'frontend_migration_userdata.migration_id')
                    ->where('frontend_migration_userdata.user_id', '=', $user->id);
            })
            ->whereNull('applied_frontend_migrations.id')
            ->select('frontend_migrations.*', 'frontend_migration_userdata.data as userdata')
            ->get()
            ->map(function (FrontendMigration|Model $migration) {
                return new MigrationToApply(
                    name: $migration->migration_name,
                    // @phpstan-ignore property.notFound
                    data: $migration->userdata
                        ? json_decode(Crypt::decrypt($migration->userdata, false), true)
                        : null
                );
            });
    }
}
