<?php

namespace App\Models\Ai\Tools;

use App\Services\AI\Tools\MCP\MCPSSEClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function tools(): HasMany
    {
        return $this->hasMany(AiTool::class, 'server_id');
    }

    // -------------------------------------------------------------------------
    // MCP Discovery
    // -------------------------------------------------------------------------

    /**
     * Connect to the MCP server and return all available tools with their schemas.
     *
     * @return array{success: bool, tools?: array, message?: string}
     */
    public function fetchServerTools(): array
    {
        try {
            $client = new MCPSSEClient(
                $this->url,
                (int) $this->discovery_timeout,
                $this->api_key ?: null
            );

            if (!$client->isAvailable()) {
                return ['success' => false, 'message' => 'Server not available'];
            }

            $response = $client->listTools();
            $tools    = $response['tools'] ?? [];

            if (empty($tools)) {
                return ['success' => false, 'message' => '  ⊗ No tools found'];
            }

            $discoveredTools = [];
            foreach ($tools as $toolInfo) {
                $toolName     = $toolInfo['name'] ?? 'unknown';
                $prefixedName = "{$this->server_label}-{$toolName}";
                $description  = $toolInfo['description'] ?? 'No description';
                $inputSchema  = $toolInfo['inputSchema'] ?? ['type' => 'object', 'properties' => []];

                $discoveredTools[] = [
                    'name'          => $prefixedName,
                    'mcp_tool_name' => $toolName,
                    'server_label'  => $this->server_label,
                    'description'   => $description,
                    'inputSchema'   => $inputSchema,
                ];
            }

            return ['success' => true, 'tools' => $discoveredTools];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => '  ✗ Failed: ' . $e->getMessage()];
        }
    }

    /**
     * Persist a single discovered tool as an AiTool DB record.
     *
     * @param array  $toolData   One entry from fetchServerTools()['tools']
     * @param string $capability The user-facing capability key (e.g. 'knowledge_base', 'web_search').
     *                           If empty, defaults to the tool's technical name.
     * @return AiTool
     */
    public function setupTools(array $toolData, string $capability = ''): AiTool
    {
        return AiTool::updateOrCreate(
            ['name' => $toolData['name']],
            [
                'description' => $toolData['description'] ?? '',
                'inputSchema' => $toolData['inputSchema'] ?? ['type' => 'object', 'properties' => []],
                'capability'  => $capability ?: $toolData['name'],
                'server_id'   => $this->id,
                'type'        => 'mcp',
                'status'      => 'active',
            ]
        );
    }
}
