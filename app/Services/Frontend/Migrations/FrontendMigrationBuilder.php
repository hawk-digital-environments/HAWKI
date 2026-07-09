<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations;

use App\Services\Frontend\Migrations\Exceptions\InvalidUserDataFinderResultException;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationUserdataRepository;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Connection;

/**
 * @api
 *
 * Registers frontend migrations during a Laravel `php artisan migrate` run.
 *
 * Frontend migrations are client-side data transformations that run in the user's browser.
 * Because the data is encrypted with the user's passkey, the server cannot read or modify
 * it directly. Instead, each migration:
 *
 * 1. Records itself in the `frontend_migrations` table.
 * 2. Optionally collects per-user context (e.g. current encrypted data shape) via a
 *    `$userDataFinder` closure. This context is encrypted and stored so the JS migration
 *    can use it when it runs on the client.
 * 3. Sends the pending migration list to the frontend on the next login. The frontend JS
 *    downloads the migration, runs it against the locally-decrypted data, and marks it done.
 *
 * Use the `FrontendMigrator` facade (backed by this class) inside a standard Laravel
 * migration's `up()` method:
 *
 * ```php
 * use App\Services\Frontend\Migrations\Facades\FrontendMigrator;
 *
 * return new class extends Migration {
 *     public function up(): void
 *     {
 *         FrontendMigrator::register(__FILE__, static function (User $user, Connection $connection): array|null {
 *             // Return current per-user data the JS migration will receive, or null to skip.
 *             return ['someKey' => $user->some_value];
 *         });
 *     }
 *
 *     public function down(): void
 *     {
 *         // Frontend migrations cannot be rolled back — always throw here.
 *         throw NoDownForFrontendMigrationsExceptionException::forMigration(__CLASS__);
 *     }
 * };
 * ```
 *
 * @see FrontendMigrator  Facade for use inside migration files.
 * @see FrontendMigrationCreator  Artisan helper that scaffolds migration file pairs.
 */
#[Singleton]
readonly class FrontendMigrationBuilder
{
    public function __construct(
        private Connection                          $connection,
        private UserRepository                      $userRepository,
        private FrontendMigrationRepository         $repository,
        private FrontendMigrationUserdataRepository $userdataRepository,
    )
    {
    }

    /**
     * Registers a frontend migration and persists any per-user data collected by `$userDataFinder`.
     *
     * The entire operation runs inside a database transaction. If `$userDataFinder` is provided
     * it is called once per user: a truthy array return is stored encrypted in
     * `frontend_migration_userdata`; a falsy or empty return is skipped. Any non-array truthy
     * return throws `InvalidUserDataFinderResultException`.
     *
     * @param string        $migrationName   The migration file path (pass `__FILE__`) or bare name.
     *                                        The filename without extension is used as the stored name.
     * @param \Closure|null $userDataFinder  Optional closure `(User $user, Connection $db): array|null`
     *                                        that returns per-user context data, or null/false to skip.
     */
    public function register(
        string        $migrationName,
        \Closure|null $userDataFinder = null
    ): void
    {
        $migrationName = $this->getRealMigrationName($migrationName);
        $this->connection->transaction(function () use ($migrationName, $userDataFinder) {
            $migration = $this->repository->insert(
                $migrationName,
                (bool)$userDataFinder
            );

            if ($userDataFinder) {
                $this->userdataRepository->dropAllForMigration($migration);
                foreach ($this->userRepository->findAll() as $user) {
                    $data = $userDataFinder($user, $this->connection);

                    if (empty($data)) {
                        continue;
                    }

                    if (!is_array($data)) {
                        throw InvalidUserDataFinderResultException::forNonArrayReturnType($migrationName);
                    }

                    $this->userdataRepository->insert($user, $migration, $data);
                }
            }
        });
    }

    private function getRealMigrationName(string $migrationName): string
    {
        return pathinfo($migrationName, PATHINFO_FILENAME);
    }
}
