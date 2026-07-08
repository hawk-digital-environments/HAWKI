<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync\Syncers;


use App\Services\Ai\ConfigFileSync\Contracts\ConfigSyncerInterface;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Ai\Tools\Repositories\McpServerRepository;
use App\Services\Ai\Tools\Values\McpServerTimeouts;
use App\Services\Ai\Tools\Values\McpServerType;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Str;

readonly class McpServerSyncer implements ConfigSyncerInterface
{
    public function __construct(
        #[Config('tools.mcp_servers')]
        private array               $mcpServerConfig,
        private McpServerRepository $serverRepository,
        private McpClientFactory    $clientFactory
    )
    {
    }

    public function getCurrentHash(): string
    {
        return md5(json_encode($this->mcpServerConfig));
    }

    public function sync(JobMetrics $metrics): void
    {
        $configuredUrls = [];
        foreach ($this->mcpServerConfig as $key => $serverConfig) {
            if (empty($serverConfig['url'])) {
                $metrics->error("MCP server config with key '{$key}' is missing a URL");
                continue;
            }

            $url = $serverConfig['url'];
            $type = empty($serverConfig['type']) ? McpServerType::SSE : McpServerType::from($serverConfig['type']);
            $label = $serverConfig['server_label'] ?? Str::headline($key);
            $description = $serverConfig['description'] ?? null;
            $requireApproval = $serverConfig['require_approval'] ?? 'never';
            $apiKey = $serverConfig['api_key'] ?? null;
            $additionalConfig = $serverConfig['config'] ?? null;

            $connectionTimeout = $serverConfig['connection_timeout'] ?? null;
            $readTimeout = $serverConfig['read_timeout'] ?? null;
            $sseIdleTimeout = $serverConfig['sse_idle_timeout'] ?? null;

            // Legacy timeouts support for backwards compatibility
            if (isset($serverConfig['timeout'])) {
                $metrics->warning("MCP server config with key '{$key}' is using the deprecated 'timeout' field. Please update to use 'connection_timeout' and 'read_timeout' instead.");
                $connectionTimeout = $readTimeout = (float)$serverConfig['timeout'];
            }
            if (isset($serverConfig['discovery_timeout'])) {
                $metrics->warning("MCP server config with key '{$key}' is using the deprecated 'discovery_timeout' field. This has no effect and should be removed.");
            }

            $timeouts = new McpServerTimeouts(
                readTimeout: $readTimeout !== null ? (float)$readTimeout : null,
                connectionTimeout: $connectionTimeout !== null ? (float)$connectionTimeout : null,
                sseIdleTimeout: $sseIdleTimeout !== null ? (float)$sseIdleTimeout : null
            );

            $configuredUrls[] = $url;

            $client = $this->clientFactory->createForConfig(
                url: $url,
                type: $type,
                config: $additionalConfig,
                apiKey: $apiKey,
                timeouts: $timeouts
            );

            if (!$client->ping()) {
                $metrics->error("Failed to ping MCP server at URL '{$url}'");
                continue;
            }

            $this->serverRepository->upsertByFile(
                url: $url,
                type: $type,
                label: $label,
                description: $description,
                requireApproval: $requireApproval,
                timeouts: $timeouts,
                apiKey: $apiKey,
                additionalConfig: $additionalConfig
            );

            $metrics->increment('MCP servers');
        }

        $this->serverRepository->removeAllConfiguredByFileNotWithUrlIn($configuredUrls);
    }
}
