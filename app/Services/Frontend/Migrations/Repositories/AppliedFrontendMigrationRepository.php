<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Repositories;


use App\Models\FrontendMigrations\AppliedFrontendMigration;
use App\Models\FrontendMigrations\FrontendMigration;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Collection;

#[UseModel(AppliedFrontendMigration::class)]
class AppliedFrontendMigrationRepository extends AbstractRepositoryWithContextualScopes
{
    /** @return Collection<int, AppliedFrontendMigration> */
    public function findAllForUser(User $user): Collection
    {
        return $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->get();
    }

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

    public function dropAllForUser(User $user): void
    {
        $this->getQueryWithoutContextualScopes('access')->where('user_id', $user->id)->delete();
    }
}
