<?php
declare(strict_types=1);


namespace App\Services\Ai\Repositories;


use App\Models\Ai\McpServer;
use App\Services\Ai\Values\McpServerTimeouts;
use App\Services\Ai\Values\McpServerType;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Illuminate\Database\Eloquent\Collection;

class McpServerRepository extends AbstractRepository
{
    /**
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
