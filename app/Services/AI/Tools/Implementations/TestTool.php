<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\AbstractTool;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Test tool for validating the tool calling implementation
 */
class TestTool extends AbstractTool
{
    public function getName(): string
    {
        return 'test_tool';
    }

    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'test_tool',
            description: 'A test tool for verifying tool calling. Only call this ONCE when explicitly asked to test tool functionality. After calling, provide a summary to the user.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'The message to echo back',
                    ],
                    'count' => [
                        'type' => 'integer',
                        'description' => 'Number of times to repeat the message',
                        'minimum' => 1,
                        'maximum' => 5,
                    ],
                ],
                'required' => ['message'],
            ],
            strict: false
        );
    }

    public function execute(array $arguments, string $toolCallId): ToolResult
    {
        Log::info('TestTool executed', [
            'tool_call_id' => $toolCallId,
            'arguments' => $arguments,
        ]);

        $message = $arguments['message'] ?? 'No message provided';
        $count = $arguments['count'] ?? 1;

        // Validate count
        if ($count < 1 || $count > 5) {
            return $this->error('Invalid count parameter: Count must be between 1 and 5', $toolCallId);
        }

        // Build response with clear instruction for the model
        $repeated = str_repeat($message . ' ', (int)$count);
        $response = [
            'status' => 'success',
            'message' => 'Test tool executed successfully. The tool echoed: HELLO WORLD!',
            'instruction' => 'Now greet the user and let them know the tool test was successful. Do not call this tool again.',
            'original_message' => $message,
            'count' => $count,
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->success($response, $toolCallId);
    }
}
