<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\StatusCheck\McpServerStatusUpdater;
use App\Services\Ai\StatusCheck\ModelStatusUpdater;
use Illuminate\Console\Command;

class CheckStatusCommand extends Command
{
    protected $signature = 'ai:check-status';

    protected $aliases = ['check:model-status', 'ai:models:check-status', 'ai:tools:check-status'];

    protected $description = 'Iterates external AI resources and updates their online status in the database.';

    /**
     * @inheritDoc
     */
    public function __construct(
        private readonly ModelStatusUpdater     $modelStatusUpdater,
        private readonly McpServerStatusUpdater $mcpServerStatusUpdater
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->output->writeln('Checking model statuses...');

        $modelMetrics = $this->modelStatusUpdater->run();

        $this->output->writeln(
            $modelMetrics->hasErrors()
                ? 'Model status check completed with errors.'
                : 'Model status check completed successfully.'
        );
        $this->output->newLine();
        $this->output->writeln('Now checking MCP server statuses...');

        $mcpMetrics = $this->mcpServerStatusUpdater->run();

        return $modelMetrics->mergeWith($mcpMetrics)->writeToCli($this->output);
    }
}
