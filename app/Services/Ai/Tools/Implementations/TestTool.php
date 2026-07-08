<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Implementations;

use App\Services\Ai\Tools\AbstractTool;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Test tool for validating the tool calling implementation
 */
class TestTool extends AbstractTool
{
    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
        $this->setMaxRuns(1);
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'test_tool';
    }

    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'A test tool for verifying tool calling. Only call this ONCE when explicitly asked to test tool functionality. After calling, provide a summary to the user.';
    }

    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()
                ->description('The message to echo back')
                ->required(),
            'count' => $schema->integer()
                ->description('Number of times to repeat the message')
                ->min(1)
                ->max(5)
                ->default(1),
        ];
    }

    public function __invoke(
        ClockInterface $clock,
        string         $message,
        int            $count = 1,
    ): array
    {
        $this->logger->info('TestTool executed', [
            'arguments' => func_get_args(),
        ]);

        // Build response with clear instruction for the model
        $repeated = str_repeat($message . ' ', $count);

        return [
            'status' => 'success',
            'message' => $repeated,
            'instruction' => 'Now greet the user and let them know the tool test was successful. Do not call this tool again.',
            'original_message' => $message,
            'count' => $count,
            'timestamp' => (new Carbon($clock->now()))->toIso8601String()
        ];
    }
}
