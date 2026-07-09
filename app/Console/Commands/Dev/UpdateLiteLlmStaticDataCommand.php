<?php
declare(strict_types=1);

namespace App\Console\Commands\Dev;

use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\StaticLiteLlmDataUpdater;
use App\Services\System\Container\SystemEnvironment;
use Illuminate\Console\Command;

class UpdateLiteLlmStaticDataCommand extends Command
{
    protected $signature = 'dev:ai:update-lite-llm-static-data';

    protected $description = 'Fetches and refreshes the static LiteLLM model data files from the LiteLLM API. Only available in the local environment.';

    public function __construct(
        private readonly SystemEnvironment        $environment,
        private readonly StaticLiteLlmDataUpdater $updater,
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->environment->isLocal()) {
            $this->error('This command can only be executed in the local environment.');
            return self::FAILURE;
        }

        return $this->updater->run()->writeToCli($this->output);
    }
}
