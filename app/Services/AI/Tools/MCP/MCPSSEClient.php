<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\MCP;

use Illuminate\Support\Facades\Log;

/**
 * SSE (Server-Sent Events) client for MCP servers
 *
 * Handles communication with MCP servers that use SSE for transport
 */
class MCPSSEClient
{
    private string $serverUrl;
    private int $timeout;

    public function __construct(string $serverUrl, int $timeout = 30)
    {
        $this->serverUrl = $serverUrl;
        $this->timeout = $timeout;
    }

    /**
     * Send a request to the MCP server and get the response
     *
     * @param string $method The MCP method to call (e.g., 'tools/list', 'tools/call')
     * @param array $params The parameters for the method
     * @return array The response from the server
     * @throws \Exception If the request fails
     */
    public function request(string $method, array $params = []): array
    {
        $requestId = uniqid('mcp_', true);

        // Build JSON-RPC request
        $request = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params,
        ];

        $jsonRequest = json_encode($request);

        Log::debug('MCP SSE Request', [
            'url' => $this->serverUrl,
            'method' => $method,
            'request' => $request,
        ]);

        // Initialize cURL for SSE
        $ch = curl_init($this->serverUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonRequest,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$responseData) {
                $responseData .= $data;
                return strlen($data);
            },
        ]);

        $responseData = '';
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($result === false) {
            throw new \Exception("MCP SSE request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \Exception("MCP SSE request failed with HTTP {$httpCode}");
        }

        // Parse SSE response
        $response = $this->parseSSEResponse($responseData, $requestId);

        Log::debug('MCP SSE Response', [
            'method' => $method,
            'response' => $response,
        ]);

        return $response;
    }

    /**
     * List available tools from the MCP server
     *
     * @return array Array of tool definitions
     */
    public function listTools(): array
    {
        try {
            $response = $this->request('tools/list');
            return $response['tools'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list MCP tools', [
                'url' => $this->serverUrl,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Call a tool on the MCP server
     *
     * @param string $toolName The name of the tool to call
     * @param array $arguments The arguments to pass to the tool
     * @return array The tool execution result
     */
    public function callTool(string $toolName, array $arguments): array
    {
        return $this->request('tools/call', [
            'name' => $toolName,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Parse SSE response data
     *
     * @param string $data The raw SSE data
     * @param string $requestId The request ID to match
     * @return array The parsed response
     * @throws \Exception If parsing fails
     */
    private function parseSSEResponse(string $data, string $requestId): array
    {
        // SSE format: data: {...}\n\n
        $lines = explode("\n", $data);
        $jsonData = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || $line[0] === ':') {
                continue;
            }

            // Parse data lines
            if (str_starts_with($line, 'data: ')) {
                $jsonString = substr($line, 6); // Remove 'data: ' prefix

                try {
                    $parsed = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);

                    // Check if this is our response
                    if (isset($parsed['id']) && $parsed['id'] === $requestId) {
                        $jsonData = $parsed;
                        break;
                    }
                } catch (\JsonException $e) {
                    Log::warning('Failed to parse SSE data line', [
                        'line' => $line,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($jsonData === null) {
            throw new \Exception('No valid JSON-RPC response found in SSE stream');
        }

        // Check for JSON-RPC error
        if (isset($jsonData['error'])) {
            $errorMsg = $jsonData['error']['message'] ?? 'Unknown MCP error';
            $errorCode = $jsonData['error']['code'] ?? -1;
            throw new \Exception("MCP Error ({$errorCode}): {$errorMsg}");
        }

        return $jsonData['result'] ?? [];
    }

    /**
     * Check if the MCP server is available
     *
     * @return bool True if the server responds
     */
    public function isAvailable(): bool
    {
        try {
            // Try to list tools as a health check
            $this->listTools();
            return true;
        } catch (\Exception $e) {
            Log::debug('MCP server not available', [
                'url' => $this->serverUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
