<?php
declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Services\Ai\ConfigFileSync\ConfigFileSyncer;
use App\Services\Ai\Tools\FunctionToolSyncer;
use Illuminate\Console\Command;

class ImportConfiguration extends Command
{
    protected $signature = 'ai:config:import';
    protected $description = 'Import deployment configuration, preserving records edited in Administration';

    public function handle(ConfigFileSyncer $syncer, FunctionToolSyncer $tools): int
    {
        $metrics = $syncer->sync(true);
        $metrics?->writeToCli($this->output);
        $toolMetrics = $tools->sync();
        $toolMetrics->writeToCli($this->output);
        return $metrics?->hasErrors() || $toolMetrics->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
