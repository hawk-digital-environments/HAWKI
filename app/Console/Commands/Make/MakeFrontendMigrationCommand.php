<?php

declare(strict_types=1);

namespace App\Console\Commands\Make;

use App\Services\Frontend\Migrations\Make\FrontendMigrationCreator;
use App\Services\Frontend\Migrations\Make\Values\EnsuredPluginMigrationHook;
use App\Services\Frontend\Plugin\PluginFs;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;

class MakeFrontendMigrationCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'make:frontend-migration {name : The name of the frontend migration}';
    protected $description = 'Create a new migration file adding a new frontend migration';

    public function __construct(
        public readonly FrontendMigrationCreator $creator,
        public readonly PluginFs $pluginFs,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $plugins = $this->pluginFs->pluginNames();

        if (empty($plugins)) {
            $this->error('No plugins found in resources/js/plugins/.');

            return;
        }

        $plugin = $this->choice(
            'Which plugin should the JS migration belong to?',
            $plugins,
        );

        $name = Str::snake(mb_trim($this->input->getArgument('name')));
        $runType = $this->choice(
            'When should the JS migration run?',
            [
                FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN => 'After user login',
                FrontendMigrationCreator::RUN_TYPE_AFTER_PASSKEY => 'After passkey verification',
                'custom' => 'Custom (you will need to manually run the migration)',
            ],
            FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN,
        );

        if ('custom' === $runType) {
            $runType = mb_trim($this->ask('Enter the run type for the JS migration (e.g. after_login, after_passkey, or any custom identifier)'));
        }

        $result = $this->creator->create(
            name: $name,
            runType: $runType,
            plugin: $plugin,
        );

        $this->info('Frontend migration created successfully.');
        $this->line('Backend migration: ' . $result->backendPath);
        $this->line('JS migration:      ' . $result->jsPath);

        if (EnsuredPluginMigrationHook::STATUS_ALREADY_CONFIGURED === $result->pluginStatus) {
            $this->line('Plugin file:        ' . $result->pluginPath . ' (already configured)');
        } else {
            $this->line('Plugin file:        ' . $result->pluginPath . ' (updated: ' . $result->pluginStatus . ')');
        }
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => ['What should the migration be named?', 'E.g. update_user_to_new_format'],
        ];
    }
}
