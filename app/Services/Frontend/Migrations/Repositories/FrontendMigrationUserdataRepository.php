<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Repositories;


use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\FrontendMigrations\FrontendMigrationUserdata;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Persists and queries encrypted per-user context data attached to frontend migrations.
 *
 * When a migration is registered with a `$userDataFinder` closure, the resulting data
 * (e.g. the current state of a user's encrypted store) is saved here, encrypted at rest.
 * The frontend JS receives this data when it picks up the migration, so it knows what to
 * transform without having to request it separately.
 */
#[UseModel(FrontendMigrationUserdata::class)]
class FrontendMigrationUserdataRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Stores the context data collected for `$user` during migration registration.
     * The model's cast layer handles encryption before persisting.
     */
    public function insert(User $user, FrontendMigration $migration, array $data): FrontendMigrationUserdata
    {
        return $this->getQueryWithoutContextualScopes()->create([
            'user_id' => $user->id,
            'migration_id' => $migration->id,
            'data' => $data,
        ]);
    }

    /** @return Collection<int, FrontendMigrationUserdata> */
    public function findAllForUser(User $user): Collection
    {
        return $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->get();
    }

    /** @return LazyCollection<int, FrontendMigrationUserdata> */
    public function findAllForMigration(FrontendMigration $migration, ?ScopeOverrides $scopeOverrides = null): LazyCollection
    {
        return $this->getQuery($scopeOverrides)->where('migration_id', $migration->id)->lazy(50);
    }

    /**
     * Looks up the context data for a specific migration and user.
     *
     * Accepts either a `FrontendMigration` model or a raw migration name string so callers
     * do not need to load the model just to do a lookup.
     */
    public function findOneForMigrationAndUser(string|FrontendMigration $migration, User $user): ?FrontendMigrationUserdata
    {
        $migrationName = $migration instanceof FrontendMigration ? $migration->migration_name : $migration;
        return $this->getQueryWithoutContextualScopes('access')
            ->whereHas('migration', function ($query) use ($migrationName) {
                $query->where('migration_name', $migrationName);
            })
            ->where('user_id', $user->id)
            ->first();
    }

    public function dropAllForMigration(FrontendMigration $migration, ?ScopeOverrides $scopeOverrides = null): void
    {
        $this->getQuery($scopeOverrides)->where('migration_id', $migration->id)->delete();
    }

    public function dropAllForUser(User $user): void
    {
        $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->delete();
    }
}
