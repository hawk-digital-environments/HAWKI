<?php

namespace App\Console\Commands\Make;

use App\Services\Frontend\Migrations\Make\FrontendMigrationCreator;
use App\Services\Frontend\Migrations\Make\PluginMigrationHookEnsurer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeFrontendMigrationCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'make:frontend-migration {name : The name of the frontend migration}';

    protected $description = 'Create a new migration file adding a new frontend migration';

    public function __construct(
        readonly FrontendMigrationCreator $creator,
        readonly Filesystem $files
    )
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $plugins = $this->availablePlugins();

        if (empty($plugins)) {
            $this->error('No plugins found in resources/js/plugins/.');
            return;
        }

        $plugin = $this->choice(
            'Which plugin should the JS migration belong to?',
            $plugins
        );

        $name = Str::snake(trim($this->input->getArgument('name')));
        $runType = $this->choice(
            'When should the JS migration run?',
            [
                FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN => 'After user login',
                FrontendMigrationCreator::RUN_TYPE_AFTER_PASSKEY => 'After passkey verification',
                'custom' => 'Custom (you will need to manually run the migration)'
            ],
            FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN
        );

        if ($runType === 'custom') {
            $runType = trim($this->ask('Enter the run type for the JS migration (e.g. after_login, after_passkey, or any custom identifier)'));
        }

        $result = $this->creator->create(
            name: $name,
            runType: $runType,
            plugin: $plugin
        );

        $this->info('Frontend migration created successfully.');
        $this->line('Backend migration: ' . $result['backendPath']);
        $this->line('JS migration:      ' . $result['jsPath']);

        $pluginPath = $result['pluginPath'];
        $pluginStatus = $result['pluginStatus'];
        if ($pluginStatus === PluginMigrationHookEnsurer::STATUS_ALREADY_CONFIGURED) {
            $this->line('Plugin file:        ' . $pluginPath . ' (already configured)');
        } else {
            $this->line('Plugin file:        ' . $pluginPath . ' (updated: ' . $pluginStatus . ')');
        }
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => ['What should the migration be named?', 'E.g. update_user_to_new_format'],
        ];
    }

    private function availablePlugins(): array
    {
        $directories = $this->files->directories(resource_path('js/plugins'));
        $plugins = [];

        foreach ($directories as $directory) {
            $pluginName = basename($directory);
            $pluginFiles = $this->files->glob($directory . '/*.plugin.ts');
            if (!empty($pluginFiles)) {
                $plugins[] = $pluginName;
            }
        }

        return $plugins;
    }
}