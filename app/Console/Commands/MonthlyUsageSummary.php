<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\UsageAnalyzerService;


class MonthlyUsageSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:summarize-monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Summarizes and cleans up usage records monthly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new UsageAnalyzerService();
        $service->summarizeAndCleanup();
        
    }
}
