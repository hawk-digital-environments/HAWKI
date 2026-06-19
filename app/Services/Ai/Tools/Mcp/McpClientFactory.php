<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Mcp;


use App\Models\Ai\McpServer;
use App\Services\Ai\Values\McpServerTimeouts;
use App\Services\Ai\Values\McpServerType;
use App\Utils\Arrays\RecursiveMerger;
use Mcp\Client\Client;
use Psr\Log\LoggerInterface;

readonly class McpClientFactory
{
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    public function createForServer(McpServer $server): HawkiMcpClient
    {
        return $this->createForConfig(
            $server->url,
            $server->type,
            $server->additional_config,
            $server->api_key,
            $server->timeouts
        );
    }

    public function createForConfig(
        string                 $url,
        McpServerType          $type,
        array|null             $config = null,
        string|null            $apiKey = null,
        McpServerTimeouts|null $timeouts = null,
    ): HawkiMcpClient
    {
        $client = new Client($this->logger);

        $headers = [];
        /** Mapped at {@see Client::connect} to {@see \Mcp\Client\Transport\HttpConfiguration} */
        $httpOptions = [];
        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        if ($timeouts !== null) {
            if ($timeouts->connectionTimeout !== null) {
                $httpOptions['connectionTimeout'] = $timeouts->connectionTimeout;
            }
            if ($timeouts->readTimeout !== null) {
                $httpOptions['readTimeout'] = $timeouts->readTimeout;
            }
            if ($timeouts->sseIdleTimeout !== null) {
                $httpOptions['sseIdleTimeout'] = $timeouts->sseIdleTimeout;
            }
        }

        $fullConfig = RecursiveMerger::merge(
            [
                'args' => [],
                'headers' => $headers,
                'http_options' => $httpOptions,
                'env' => []
            ],
            $config ?? []
        );

        $clientArgs = $type === McpServerType::STDIO
            ? $fullConfig['args']
            : $fullConfig['headers'];
        $clientEnv = $type === McpServerType::STDIO
            ? $fullConfig['env']
            : $fullConfig['http_options'];

        return new HawkiMcpClient(
            url: $url,
            sessionFactory: fn() => $client->connect(
                commandOrUrl: $url,
                args: $clientArgs,
                env: $clientEnv,
                readTimeout: (float)($timeout ?? 10)
            ),
            logger: $this->logger
        );
    }
}
