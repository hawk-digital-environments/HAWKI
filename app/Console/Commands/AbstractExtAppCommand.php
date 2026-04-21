<?php
declare(strict_types=1);


namespace App\Console\Commands;


use Illuminate\Console\Command;

abstract class AbstractExtAppCommand extends Command
{
    protected function assertAppsAreEnabled(): void
    {
        if (!config('external_access.enabled')) {
            throw new \RuntimeException("External access is not enabled in the configuration. Please enable using the \"ALLOW_EXTERNAL_COMMUNICATION\" environment variable.");
        }
        
        if (!config('external_access.apps')) {
            throw new \RuntimeException("External access for apps is not enabled in the configuration. Please enable using the \"ALLOW_EXTERNAL_APPS\" environment variable.");
        }
    }
}
