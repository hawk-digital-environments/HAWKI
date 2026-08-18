<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Support\Str;

readonly class FrontendMigrationCreator
{
    public const string RUN_TYPE_AFTER_LOGIN = 'after_login';

    public const string RUN_TYPE_AFTER_PASSKEY = 'after_passkey';

    public function __construct(
        private BackendMigrationCreator       $phpMigrationCreator,
        private JsMigrationCreator            $jsMigrationCreator,
        private CarbonClockInterface          $clock,
        private PluginMigrationHookEnsurer    $pluginMigrationHookEnsurer
    )
    {
    }

    /**
     * @param string $name   Snake-case migration name without timestamp or run-type prefix.
     * @param string $runType When the JS migration executes.
     * @param string $plugin  Plugin directory name under resources/js/plugins/.
     * @return array{backendPath: string, jsPath: string, pluginPath: string, pluginStatus: string}
     */
    public function create(string $name, string $runType, string $plugin): array
    {
        $migrationName = $this->clock->now()->format('Y_m_d_His') . '_' . Str::snake($runType) . '_' . Str::snake($name);

        $phpMigrationPath = $this->phpMigrationCreator->create($migrationName, database_path('migrations'));
        $jsMigrationPath = $this->jsMigrationCreator->create(
            $migrationName,
            resource_path('js/plugins/' . $plugin . '/migrations/' . Str::snake($runType))
        );

        $pluginResult = $this->pluginMigrationHookEnsurer->ensure($plugin);

        return [
            'backendPath' => $phpMigrationPath,
            'jsPath' => $jsMigrationPath,
            'pluginPath' => $pluginResult['path'],
            'pluginStatus' => $pluginResult['status'],
        ];
    }
}