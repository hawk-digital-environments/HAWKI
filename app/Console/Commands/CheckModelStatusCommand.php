<?php

namespace App\Console\Commands;

use App\Services\AI\AiService;
use App\Services\AI\Db\ModelStatusDb;
use Illuminate\Console\Command;

class CheckModelStatusCommand extends Command
{
    protected $signature = 'check:model-status';
    
    protected $description = 'Iterates all AI models and checks their online status via their respective providers, updating the database accordingly.';
    
    /**
     * @inheritDoc
     */
    public function __construct(
        private readonly ModelStatusDb $modelStatusDb,
        private readonly AiService     $aiService
    )
    {
        parent::__construct();
    }
    
    public function handle(): void
    {
        $this->output->writeln('Starting model status check...');
        
        $models = $this->aiService->getAvailableModels()->models;
        foreach ($models as $model) {
            $this->output->write("Checking model: {$model->getId()}");
            $status = $model->getClient()->getStatus();
            $this->output->writeln(" is " . $status->value);
            $this->modelStatusDb->setModelStatus($model, $status);
        }
        
        $this->output->writeln('Model status check completed. Checked ' . $models->count() . ' models.');
    }
}
