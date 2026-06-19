<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Implementations;

use App\Services\Ai\Tools\AbstractTool;
use Carbon\Carbon;
use NeuronAI\Exceptions\ToolException;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;

/**
 * Test tool for validating the tool calling implementation
 */
class TestTool extends AbstractTool
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ClockInterface  $clock = new Clock()
    )
    {
        parent::__construct();
        $this->setMaxRuns(1);
    }

    /**
     * @inheritDoc
     */
    protected function name(): string
    {
        return 'test_tool';
    }

    /**
     * @inheritDoc
     */
    protected function description(): string
    {
        return 'A test tool for verifying tool calling. Only call this ONCE when explicitly asked to test tool functionality. After calling, provide a summary to the user.';
    }

    /**
     * @inheritDoc
     */
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'message',
                type: PropertyType::STRING,
                description: 'The message to echo back',
                required: true
            ),
            new ToolProperty(
                name: 'count',
                type: PropertyType::INTEGER,
                description: 'Number of times to repeat the message',
                required: false
            ),
        ];
    }

    public function __invoke(
        string $message,
        int    $count = 1
    ): array
    {
        $this->logger->info('TestTool executed', [
            'tool_call_id' => $this->getCallId(),
            'arguments' => func_get_args(),
        ]);

        // Validate count
        if ($count < 1 || $count > 5) {
            throw new ToolException('Invalid count parameter: Count must be between 1 and 5');
        }

        // Build response with clear instruction for the model
        $repeated = str_repeat($message . ' ', $count);

        return [
            'status' => 'success',
            'message' => $repeated,
            'instruction' => 'Now greet the user and let them know the tool test was successful. Do not call this tool again.',
            'original_message' => $message,
            'count' => $count,
            'timestamp' => (new Carbon($this->clock->now()))->toIso8601String()
        ];
    }
}
