<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Repositories;


use App\Models\FrontendMigrations\AppliedFrontendMigration;
use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persists and queries which frontend migrations have already been applied for a given user.
 *
 * A row in `applied_frontend_migrations` means "this user's browser has already run this
 * migration and their data is up to date." The frontend migration runner creates these
 * records via the API after successfully completing each migration.
 */
#[UseModel(AppliedFrontendMigration::class)]
class AppliedFrontendMigrationRepository extends AbstractRepositoryWithContextualScopes
{
    /** @return Collection<int, AppliedFrontendMigration> */
    public function findAllForUser(User $user): Collection
    {
        return $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->get();
    }

    /**
     * Records that `$migration` has been applied for `$user`.
     * Idempotent — returns the existing record if one already exists.
     */
    public function applyForUser(FrontendMigration $migration, User $user): AppliedFrontendMigration
    {
        $existing = $this->getQueryWithoutContextualScopes('access')
            ->where('user_id', $user->id)
            ->where('migration_id', $migration->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->getQueryWithoutContextualScopes()->create([
            'user_id' => $user->id,
            'migration_id' => $migration->id,
        ]);
    }

    /**
     * Bulk-marks all given migrations as applied for a newly created user.
     *
     * Purges any existing records for the user first to ensure a clean state,
     * then inserts one row per migration.
     *
     * @param Collection<int, FrontendMigration> $migrations
     */
    public function applyAllForNewUser(Collection $migrations, User $user): void
    {
        // Just to be sure, drop all existing for the user.
        $this->dropAllForUser($user);

        $migrations->map(fn(FrontendMigration $migration) => $this->getQueryWithoutContextualScopes()->create([
            'user_id' => $user->id,
            'migration_id' => $migration->id,
        ]));
    }

    /**
     * Removes all applied-migration records for `$user`, effectively resetting them
     * so all migrations will be delivered again on next login.
     */
    public function dropAllForUser(User $user): void
    {
        $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->delete();
    }
}
