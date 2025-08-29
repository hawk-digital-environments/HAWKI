<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\Providers\GWDG\GWDGProvider;

class GetGWDGList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:list-gwdg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $config = config('model_providers');
        $service = new GWDGProvider($config['providers']['gwdg']);
        $list = $service->checkAllModelsStatus();
        $jsonString = json_encode($list, JSON_PRETTY_PRINT);
        $this->info($jsonString);
    }
}
