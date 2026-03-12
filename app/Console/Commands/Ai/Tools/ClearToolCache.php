<?php

namespace App\Console\Commands\Ai\Tools;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearToolCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:tools:clear';

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
        Cache::forget('mcp-tools');
    }
}
