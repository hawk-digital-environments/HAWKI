<?php

namespace App\Console\Commands\Make;

use App\Services\Frontend\Migrations\Make\FrontendMigrationCreator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;

class MakeFrontendMigrationCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'make:frontend-migration {name : The name of the frontend migration}';

    protected $description = 'Create a new migration file adding a new frontend migration';

    public function __construct(
        readonly FrontendMigrationCreator $creator
    )
    {
        parent::__construct();
    }

    public function handle(): void
    {
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

        [$backendMigrationPath, $jsMigrationPath] = $this->creator->create(
            name: $name,
            runType: $runType
        );

        $this->info("Frontend migration created successfully.");
        $this->line("Backend migration: " . $backendMigrationPath);
        $this->line("JS migration: " . $jsMigrationPath);
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => ['What should the migration be named?', 'E.g. update_user_to_new_format'],
        ];
    }
}
