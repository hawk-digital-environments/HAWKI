<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\AbstractMCPTool;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\Value\ToolDefinition;
use Illuminate\Support\Facades\Log;

/**
 * D&D MCP Tool - Dungeons and Dragons dice rolling tool
 *
 * This tool connects to an external MCP server that provides
 * dice rolling functionality for D&D games.
 *
 * Execution Strategy:
 * - ExecutionStrategy::MCP - Model calls MCP server directly
 * - ExecutionStrategy::FUNCTION_CALL - HAWKI orchestrates the call
 */
class DmcpTool extends AbstractMCPTool
{
    private const DEFAULT_SERVER_URL = 'https://dmcp-server.deno.dev/sse';

    private array $serverConfig;

    public function __construct(array $serverConfig = [])
    {
        $this->serverConfig = array_merge([
            'url' => self::DEFAULT_SERVER_URL,
            'label' => 'D&D Dice Roller',
            'require_approval' => 'never',
        ], $serverConfig);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'dice_roll';
    }

    /**
     * @inheritDoc
     */
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'dice_roll',
            description: 'Roll dice for Dungeons & Dragons. Supports standard D&D dice notation (e.g., "1d20", "2d6+3", "1d20 advantage", "1d20 disadvantage").',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'dice' => [
                        'type' => 'string',
                        'description' => 'The dice notation to roll (e.g., "1d20", "2d6+3", "1d20 advantage")',
                    ],
                    'reason' => [
                        'type' => 'string',
                        'description' => 'Optional reason for the roll (e.g., "Attack roll", "Stealth check")',
                    ],
                ],
                'required' => ['dice'],
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
        $dice = $arguments['dice'] ?? '';
        $reason = $arguments['reason'] ?? null;

        if (empty($dice)) {
            throw new \InvalidArgumentException('Dice notation is required');
        }

        $serverUrl = $this->serverConfig['url'] ?? self::DEFAULT_SERVER_URL;

        Log::info('DMCP tool executing', [
            'dice' => $dice,
            'reason' => $reason,
            'server' => $serverUrl,
        ]);

        // Create client and call the MCP server
        $client = new MCPSSEClient($serverUrl);

        $mcpParams = ['dice' => $dice];
        if ($reason) {
            $mcpParams['reason'] = $reason;
        }

        $response = $client->callTool('roll', $mcpParams);

        // Format the response for the AI model
        $result = [
            'dice' => $dice,
            'result' => $response['result'] ?? 'Unknown result',
            'total' => $response['total'] ?? null,
            'rolls' => $response['rolls'] ?? null,
        ];

        if ($reason) {
            $result['reason'] = $reason;
        }

        Log::info('DMCP tool executed successfully', ['result' => $result]);

        return $result;
    }
}
