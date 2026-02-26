<?php

namespace App\Models\Tools;

use App\Services\AI\Tools\MCP\MCPSSEClient;
use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    protected $fillable = [
        'url',
        'server_label',
        'description',
        'require_approval',
        'timeout',
        'discovery_timeout',
        'api_key',
    ];


    public function fetchServerTools(): array{
        try {

            $client = new MCPSSEClient($this->url,
                                       $this->discovery_timeout,
                                       $this->api_key);

            // Check server availability
            if (!$client->isAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Server not available',
                ];
            }

            // List tools from server
            $response = $client->listTools();
            $tools = $response['tools'] ?? [];

            if (empty($tools)) {
                return [
                    'success' => false,
                    'message' => '  ⊗ No tools found',
                ];
            }

            $discoveredTools = [];
            foreach ($tools as $toolInfo) {
                $toolName = $toolInfo['name'] ?? 'unknown';
                $prefixedName = "{$this->server_label}-{$toolName}";
                $description = $toolInfo['description'] ?? 'No description';

                $discoveredTools[] = [
                    'name' => $prefixedName,
                    'mcp_tool_name' => $toolName,
                    'server_label' => $this->server_label,
                    'description' => $description,
                ];
            }

            return [
                'success' => true,
                'tools' => $discoveredTools,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "  ✗ Failed: " . $e->getMessage(),
            ];
        }
    }


    public function setupTools(array $toolData){




    }


}
