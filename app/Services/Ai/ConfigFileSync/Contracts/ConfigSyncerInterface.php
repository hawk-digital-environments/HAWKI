<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync\Contracts;


use App\Utils\JobMetrics;

interface ConfigSyncerInterface
{
    /**
     * Get a hash representing the current state of the config file(s) that this syncer manages. This can be used to determine if the config has changed since the last sync.
     * This is used to avoid unnecessary syncs if the config file(s) have not changed.
     * @return string
     */
    public function getCurrentHash(): string;

    /**
     * Perform the sync operation, which should read the config file(s) and update the system accordingly. The provided JobMetrics object can be used to track metrics about the sync operation, such as how many entries were added, updated, or removed.
     * Update the JobMetrics object with relevant counters and error messages during the sync process. For example, if this syncer manages MCP server configurations, you might increment a "mcp_servers_added" counter for each new server added, or a "mcp_servers_removed" counter for each server removed. If any errors occur during the sync (e.g., invalid config format, failed database operations), use the `withError()` method to record an error message in the JobMetrics.
     */
    public function sync(JobMetrics $metrics): void;
}
