<?php

declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Make;

use App\Services\Frontend\Migrations\Make\Values\CreatedFrontendMigration;
use App\Services\Frontend\Plugin\PluginFs;
use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Support\Str;

readonly class FrontendMigrationCreator
{
    public const string RUN_TYPE_AFTER_LOGIN = 'after_login';
    public const string RUN_TYPE_AFTER_PASSKEY = 'after_passkey';

    public function __construct(
        private BackendMigrationCreator $phpMigrationCreator,
        private JsMigrationCreator $jsMigrationCreator,
        private CarbonClockInterface $clock,
        private PluginMigrationHookEnsurer $pluginMigrationHookEnsurer,
        private PluginFs $pluginFs,
    ) {
    }

    /**
     * Scaffolds the paired backend/JS migration files and ensures the plugin's `migrations()` hook.
     *
     * @param string $name    snake-case migration name without timestamp or run-type prefix
     * @param string $runType when the JS migration executes
     * @param string $plugin  plugin directory name under resources/js/plugins/
     */
    public function create(string $name, string $runType, string $plugin): CreatedFrontendMigration
    {
        $migrationName = $this->clock->now()->format('Y_m_d_His') . '_' . Str::snake($runType) . '_' . Str::snake($name);

        $phpMigrationPath = $this->phpMigrationCreator->create($migrationName, database_path('migrations'));
        $jsMigrationPath = $this->jsMigrationCreator->create(
            $migrationName,
            $this->pluginFs->migrationsDirectoryForRunType($plugin, $runType),
        );

        $pluginResult = $this->pluginMigrationHookEnsurer->ensure($plugin);

        return new CreatedFrontendMigration(
            backendPath: $phpMigrationPath,
            jsPath: $jsMigrationPath,
            pluginPath: $pluginResult->path,
            pluginStatus: $pluginResult->status,
        );
    }
}
