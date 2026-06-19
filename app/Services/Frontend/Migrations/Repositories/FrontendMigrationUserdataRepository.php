<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Repositories;


use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\FrontendMigrations\FrontendMigrationUserdata;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Support\Collection;

#[UseModel(FrontendMigrationUserdata::class)]
class FrontendMigrationUserdataRepository extends AbstractRepositoryWithContextualScopes
{
    public function insert(User $user, FrontendMigration $migration, array $data): FrontendMigrationUserdata
    {
        return $this->getQueryWithoutContextualScopes()->create([
            'user_id' => $user->id,
            'migration_id' => $migration->id,
            'data' => $data,
        ]);
    }

    /** @return Collection<int, FrontendMigrationUserdata> */
    public function findAll(?ScopeOverrides $scopeOverrides = null): Collection
    {
        return $this->getQuery($scopeOverrides)->get();
    }

    /** @return Collection<int, FrontendMigrationUserdata> */
    public function findAllForUser(User $user): Collection
    {
        return $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->get();
    }

    /** @return Collection<int, FrontendMigrationUserdata> */
    public function findAllForMigration(FrontendMigration $migration, ?ScopeOverrides $scopeOverrides = null): Collection
    {
        return $this->getQuery($scopeOverrides)->where('migration_id', $migration->id)->lazy(50);
    }

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
