<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\MCP\MCPToolAdapter;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * D&D MCP Tool - Dungeons and Dragons dice rolling tool
 *
 * This tool connects to an external MCP server that provides
 * dice rolling functionality for D&D games.
 */
class DmcpTool extends MCPToolAdapter
{
    public function __construct(array $serverConfig)
    {
        parent::__construct($serverConfig);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'dmcp_roll_dice';
    }

    /**
     * @inheritDoc
     */
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'dmcp_roll_dice',
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
     * @inheritDoc
     */
    public function getMCPCategory(): string
    {
        return 'gaming';
    }


    /**
     * @inheritDoc
     */
    public function execute(array $arguments, string $toolCallId): ToolResult
    {
        // Get server URL from config
        $serverUrl = $this->serverConfig['server_url'] ?? '';

        if (empty($serverUrl)) {
            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: ['error' => 'DMCP server URL not configured'],
                success: false,
                error: 'Server not configured'
            );
        }

        // Validate arguments
        $dice = $arguments['dice'] ?? '';
        $reason = $arguments['reason'] ?? null;

        if (empty($dice)) {
            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: ['error' => 'Dice notation is required'],
                success: false,
                error: 'Missing dice parameter'
            );
        }

        Log::info('DMCP tool executing', [
            'tool_call_id' => $toolCallId,
            'dice' => $dice,
            'reason' => $reason,
        ]);

        try {
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

            Log::info('DMCP tool executed successfully', [
                'tool_call_id' => $toolCallId,
                'result' => $result,
            ]);

            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: $result,
                success: true
            );
        } catch (\Exception $e) {
            Log::error('DMCP tool execution failed', [
                'tool_call_id' => $toolCallId,
                'dice' => $dice,
                'error' => $e->getMessage(),
            ]);

            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: ['error' => 'Failed to roll dice: ' . $e->getMessage()],
                success: false,
                error: $e->getMessage()
            );
        }
    }
}
