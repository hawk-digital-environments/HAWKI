<?php
declare(strict_types=1);


namespace App\Console\Commands\ExtApp;


use App\Services\ExtApp\Config\ExtAppConfig;
use Illuminate\Console\Command;

abstract class AbstractExtAppCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function __construct(
        protected ExtAppConfig $extAppConfig
    )
    {
        parent::__construct();
    }

    protected function assertAppsAreEnabled(): void
    {
        if (!$this->extAppConfig->externalAccess) {
            $this->output->error("External access is not enabled in the configuration. Please enable using the \"ALLOW_EXTERNAL_COMMUNICATION\" environment variable.");
            exit(1);
        }

        if (!config('external_access.apps')) {
            $this->output->error("External access for apps is not enabled in the configuration. Please enable using the \"ALLOW_EXTERNAL_APPS\" environment variable.");
            exit(1);
        }
    }
}
