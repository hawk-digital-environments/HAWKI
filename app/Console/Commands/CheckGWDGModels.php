<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckGWDGModels extends Command
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
        $list = $this->checkAllModelsStatus();
        $jsonString = json_encode($list, JSON_PRETTY_PRINT);
        $this->info($jsonString);
    }

    // /**
    // * Ping the API to check status of all models
    // */
    public function checkAllModelsStatus(): array
    {
        $response = $this->pingProvider();
        return json_decode($response, true)['data'];
    }


    /**
     * Get status of all models
     *
     * @return ?string
     */
    protected function pingProvider(): ?string
    {
        $config = config('model_providers.providers.gwdg');
        $url = $config['ping_url'];
        $apiKey = $config['api_key'];

        try {
            return Http::withToken($apiKey)
                ->timeout(5) // Set a short timeout
                ->get($url);
        } catch (\Exception $e) {
            return null;
        }

    }

}
