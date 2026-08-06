<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Support\Str;

/**
 * Scaffolds a new frontend migration by creating a paired set of stub files:
 * one Laravel PHP migration and one TypeScript migration for the frontend runner.
 *
 * Called by `MakeFrontendMigrationCommand` (`php artisan make:frontend-migration`).
 *
 * The generated filename follows the pattern:
 * `{timestamp}_{run_type}_{name}` — e.g. `2024_01_15_120000_after_login_update_user_prefs`.
 * The PHP stub lands in `database/migrations/` and the TS stub in
 * `resources/js/migrations/{run_type}/`.
 */
readonly class FrontendMigrationCreator
{
    /** Run-type constant for migrations that execute immediately after the user logs in. */
    public const string RUN_TYPE_AFTER_LOGIN = 'after_login';

    /** Run-type constant for migrations that execute after the user has unlocked their passkey. */
    public const string RUN_TYPE_AFTER_PASSKEY = 'after_passkey';

    public function __construct(
        private BackendMigrationCreator $phpMigrationCreator,
        private JsMigrationCreator      $jsMigrationCreator,
        private CarbonClockInterface    $clock
    )
    {
    }

    /**
     * Creates both stub files and returns their absolute paths.
     *
     * @param string $name Snake-case migration name without timestamp or run-type prefix,
     *                          e.g. `update_user_prefs`.
     * @param string $runType When the JS migration should execute; use the `RUN_TYPE_*` constants
     *                          or any custom string that matches a folder in `resources/js/migrations/`.
     * @return array{0: string, 1: string} Tuple of [phpMigrationPath, jsMigrationPath].
     */
    public function create(string $name, string $runType): array
    {
        $migrationName = $this->clock->now()->format('Y_m_d_His') . '_' . Str::snake($runType) . '_' . Str::snake($name);

        $phpMigrationPath = $this->phpMigrationCreator->create($migrationName, database_path('migrations'));
        $jsMigrationPath = $this->jsMigrationCreator->create(
            $migrationName,
            resource_path('js/migrations/' . Str::snake($runType))
        );

        return [$phpMigrationPath, $jsMigrationPath];
    }
}
