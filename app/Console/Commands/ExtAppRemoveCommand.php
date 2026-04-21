<?php

namespace App\Console\Commands;

use App\Models\ExtApp;
use App\Services\ExtApp\AppRemover;
use App\Services\ExtApp\Db\AppDb;

class ExtAppRemoveCommand extends AbstractExtAppCommand
{
    public function __construct(
        private readonly AppDb      $appDb,
        private readonly AppRemover $appRemover
    )
    {
        parent::__construct();
    }
    
    protected $signature = 'ext-app:remove';
    
    protected $description = 'Deletes a registered external app from the system.';
    
    public function handle(): void
    {
        $this->assertAppsAreEnabled();
        
        $this->info("You are about to delete an external app from the system. This action is irreversible!");
        
        $apps = $this->appDb->findAll();
        
        if ($apps->isEmpty()) {
            $this->info('No apps found. You can create one with the command `apps:create`.');
            return;
        }
        
        $appChoices = $apps
            ->sort(static fn(ExtApp $a, ExtApp $b) => $a->name <=> $b->name)
            ->mapWithKeys(static function ($app) {
                // Needs 'app_' prefix because: https://stackoverflow.com/questions/39828589/laravel-choice-command-numeric-key
                return [$app->id . '_app' => $app->name . ' (ID: ' . $app->id . ')'];
            })->toArray();
        
        $appId = $this->choice(
            'Please select the app you want to delete',
            $appChoices,
        );
        
        if (!$appId) {
            $this->error('No app selected. Aborting deletion.');
            return;
        }
        
        $app = $apps->first(static fn(ExtApp $app) => $app->id === (int)substr($appId, 0, -4)); // Stripp "app_" prefix
        
        if (!$this->confirm('Are you sure you want to delete the app: ' . $app->name . '? This action cannot be undone.')) {
            $this->info('Deletion cancelled.');
            return;
        }
        
        try {
            $this->appRemover->remove($app);
            $this->info('App deleted successfully.');
        } catch (\Throwable $e) {
            $this->error('An error occurred while deleting the app: ' . $e->getMessage());
        }
    }
}
