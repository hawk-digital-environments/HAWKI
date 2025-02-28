<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\UsageAnalyzerService;
use App\Models\Records\UsageRecord;


class FetchUserRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-user-records';

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
        $records = UsageRecord::all();
        $sumPromptToken = $records->pluck('prompt_tokens')->sum();
        $sumCompletionToken = $records->pluck('completion_tokens')->sum();

        $this->info('Sum of prompt_tokens: ' . $sumPromptToken);
        $this->info('Sum of completion_tokens: ' . $sumCompletionToken);

    }
}
