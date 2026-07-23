<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Repositories;


use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Values\McpServerTimeouts;
use App\Services\Ai\Tools\Values\McpServerType;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository for the {@see McpServer} Eloquent model.
 *
 * Centralises all database access for MCP server records, providing targeted finders
 * and write operations used by the status-check, config-sync, and tool-sync subsystems.
 *
 * Notable design points:
 *  - Servers can be managed manually (via the UI/CLI) or automatically via config files.
 *    The `added_by_file` flag distinguishes them; file-managed servers are removed by
 *    {@see removeAllConfiguredByFileNotWithUrlIn()} when they disappear from the config.
 *  - {@see setOnlineStatus()} is the only sanctioned way to update a server's status —
 *    keeping the mutation path narrow and auditable.
 *  - {@see upsertByFile()} always sets `added_by_file = true`; {@see upsert()} lets the
 *    caller control that flag for UI/CLI-driven changes.
 */
class McpServerRepository extends AbstractRepository
{
    /**
     * Returns all servers currently marked as {@see OnlineStatus::ONLINE}.
     *
     * @return Collection<int, McpServer>
     */
    public function findAllOnline(): Collection
    {
        return $this->getQuery()->where('status', OnlineStatus::ONLINE)->get();
    }

    /**
     * @return Collection<int, McpServer>
     */
    public function findAllAddedByFile(): Collection
    {
        return $this->getQuery()->where('added_by_file', true)->get();
    }

    /**
     * Remove all McpServer entries that were added by file and whose URL is not in the provided list
     */
    public function removeAllConfiguredByFileNotWithUrlIn(array $urls): void
    {
        $this->getQuery()->where('added_by_file', true)
            ->whereNotIn('url', $urls)
            ->delete();
    }

    /**
     * Updates the online status of the given server
     */
    public function setOnlineStatus(McpServer $server, OnlineStatus $status): void
    {
        $server->status = $status;
        $server->save();
    }

    /**
     * Creates or updates an MCP server record matched by URL.
     *
     * The URL is the natural unique key for MCP servers — changing it will create a new record
     * rather than updating the existing one, so URL changes require a manual cleanup of the
     * orphaned row. The `api_key` is stored encrypted; pass null to clear it.
     *
     * @param string|null $requireApproval  Approval policy string (e.g. `'never'`, `'always'`).
     *                                      Defaults to `'never'` when null.
     */
    public function upsert(
        string                 $url,
        McpServerType          $type,
        string                 $label,
        string|null            $description,
        string|null            $requireApproval,
        McpServerTimeouts|null $timeouts,
        string|null            $apiKey,
        array|null             $additionalConfig,
        bool                   $addedByFile = false,
    ): McpServer
    {
        return $this->getQuery()->updateOrCreate(
            ['url' => $url],
            [
                'server_label' => $label,
                'type' => $type,
                'description' => $description,
                'require_approval' => $requireApproval ?? 'never',
                'timeouts' => $timeouts ?? new McpServerTimeouts(),
                'api_key' => $apiKey ?? null,
                'additional_config' => $additionalConfig ?? null,
                'added_by_file' => $addedByFile,
            ]
        );
    }

    /**
     * Convenience wrapper around {@see upsert()} that always sets `added_by_file = true`.
     * Called by the config-file sync path so these servers can be identified and cleaned up
     * when they are removed from the configuration.
     */
    public function upsertByFile(
        string                 $url,
        McpServerType          $type,
        string                 $label,
        string|null            $description,
        string|null            $requireApproval,
        McpServerTimeouts|null $timeouts,
        string|null            $apiKey,
        array|null             $additionalConfig
    ): McpServer
    {
        return $this->upsert($url, $type, $label, $description, $requireApproval, $timeouts, $apiKey, $additionalConfig, addedByFile: true);
    }
}
