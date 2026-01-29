<?php

namespace App\Console\Commands;

use App\Models\ExtApp;
use App\Services\ExtApp\Db\AppDb;

class ExtAppListCommand extends AbstractExtAppCommand
{
    protected $signature = 'ext-app:list';
    
    protected $description = 'Lists all external apps that are currently registered in the system.';
    
    public function __construct(
        private readonly AppDb $appDb
    )
    {
        parent::__construct();
    }
    
    public function handle(): void
    {
        $this->assertAppsAreEnabled();
        
        $apps = $this->appDb->findAll();
        
        if ($apps->isEmpty()) {
            $this->info('No apps found. You can create one with the command `ext-app:create`.');
            return;
        }
        
        /** @var array<string,ExtApp> $healthyApps */
        $healthyApps = [];
        /** @var array{0:string, 1:ExtApp}[] $brokenApps */
        $brokenApps = [];
        
        foreach ($apps->sort(static fn(ExtApp $a, ExtApp $b) => $a->name <=> $b->name) as $app) {
            $status = $app->get_healthy_status();
            if ($status === ExtApp::HEALTHY_STATUS) {
                $healthyApps[$app->id] = $app;
            } else {
                $brokenApps[$app->id] = [$status, $app];
            }
        }
        
        $this->renderBrokenApps($brokenApps);
        $this->renderHealthyApps($healthyApps);
    }
    
    /**
     * @param array{0:string, 1:ExtApp} $brokenApps
     */
    protected function renderBrokenApps(array $brokenApps): void
    {
        if (empty($brokenApps)) {
            return;
        }
        
        $this->warn('ATTENTION: Some external apps are not healthy!');
        
        $statusToHuman = static function (string $status): string {
            $humanReadable = str_replace('_', ' ', $status);
            return ucfirst($humanReadable);
        };
        
        $rows = [];
        foreach ($brokenApps as $key => $args) {
            [$status, $app] = $args;
            $rows[$key] = [
                $app->id,
                $app->name,
                substr($app->description, 0, 50) . (strlen($app->description) > 50 ? '...' : ''),
                $app->created_at->toDateTimeString(),
                $statusToHuman($status),
            ];
        }
        
        $this->table(
            ['ID', 'Name', 'Description', 'Created At', 'Status'],
            $rows
        );
    }
    
    /**
     * @param ExtApp[] $healthyApps
     */
    protected function renderHealthyApps(array $healthyApps): void
    {
        if (empty($healthyApps)) {
            return;
        }
        
        $this->info('Registered Apps:');
        
        $rows = [];
        foreach ($healthyApps as $app) {
            $rows[] = [
                $app->id,
                $app->name,
                substr($app->description, 0, 50) . (strlen($app->description) > 50 ? '...' : ''),
                $app->created_at->toDateTimeString(),
            ];
        }
        
        $this->table(
            ['ID', 'Name', 'Description', 'Created At'],
            $rows
        );
    }
}
