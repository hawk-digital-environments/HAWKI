<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\AbstractMCPTool;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\Value\ToolDefinition;
use Illuminate\Support\Facades\Log;

/**
 * RAWKI Web Search Tool
 *
 * This tool connects to the RAWKI MCP server to perform web searches
 * using Brave or Tavily search providers.
 *
 * Execution Strategy:
 * - ExecutionStrategy::MCP - HAWKI orchestrates calls to RAWKI MCP server
 * - ExecutionStrategy::FUNCTION_CALL - Same execution, just different config strategy
 */
class RawkiSearchTool extends AbstractMCPTool
{
    private const DEFAULT_SERVER_URL = 'http://127.0.0.1:8080/mcp/rawki';

    private array $serverConfig;

    public function __construct(array $serverConfig = [])
    {
        $this->serverConfig = array_merge([
            'url' => self::DEFAULT_SERVER_URL,
            'server_label' => 'search-tool',
            'description' => 'RAWKI Web Search',
            'require_approval' => 'never',
        ], $serverConfig);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'web_search';
    }

    /**
     * @inheritDoc
     */
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'web_search',
            description: 'Run a web search via Brave or Tavily search provider. Use this when the user asks for current information, news, research, or anything that requires up-to-date web data.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query. Be specific and concise.',
                    ],
                    'max_results' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of search results to return (1-20). Default: 5',
                        'minimum' => 1,
                        'maximum' => 20,
                    ],
                ],
                'required' => ['query'],
            ],
            strict: false
        );
    }

    /**
     * Get MCP server configuration
     */
    public function getMCPServerConfig(): array
    {
        return $this->serverConfig;
    }

    /**
     * Execute MCP-specific logic
     * Called by AbstractMCPTool after availability checks
     */
    protected function executeMCP(array $arguments): mixed
    {
        // Validate arguments
        $query = $arguments['query'] ?? '';
        $maxResults = $arguments['max_results'] ?? 5;

        if (empty($query)) {
            throw new \InvalidArgumentException('Search query is required');
        }

        $serverUrl = $this->serverConfig['url'] ?? self::DEFAULT_SERVER_URL;

        Log::info('RAWKI search tool executing', [
            'query' => $query,
            'max_results' => $maxResults,
            'server' => $serverUrl,
        ]);

        // Create client and call the RAWKI MCP server
        $client = new MCPSSEClient($serverUrl);

        $mcpParams = [
            'query' => $query,
            'max_results' => $maxResults,
        ];

        // The tool name in RAWKI MCP server is 'search'
        $response = $client->callTool('search-tool', $mcpParams);

        Log::info('RAWKI search tool executed successfully', [
            'query' => $query,
            'result_count' => count($response['results'] ?? []),
        ]);

        // Format response for the AI model
        $result = [
            'query' => $query,
            'results' => json_encode($response['result']) . 'IMPORTANT: always add the references like urls, titles etc. when using the results.'?? [],
        ];

        Log::info('RESULT => ', $result);
        return $result;
    }

    /**
     * Check if the RAWKI MCP server is available
     */
    public function isServerAvailable(): bool
    {
        $url = $this->serverConfig['url'] ?? self::DEFAULT_SERVER_URL;

        try {
            // Try to connect to the server with a short timeout
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Server is available if we get any HTTP response
            return $httpCode > 0;
        } catch (\Exception $e) {
            Log::warning('RAWKI MCP server not available', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
