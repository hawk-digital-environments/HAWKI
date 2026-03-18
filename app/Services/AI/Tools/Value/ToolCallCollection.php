<?php
declare(strict_types=1);


namespace App\Services\AI\Tools\Value;


use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Traversable;

/**
 * @extends \IteratorAggregate<int, ToolCall>
 */
readonly class ToolCallCollection implements \IteratorAggregate, \JsonSerializable, \Countable
{
    /**
     * @var array|ToolCall[]
     */
    private array $toolCalls;

    public function __construct(
        ToolCall ...$toolCalls
    )
    {
        $this->toolCalls = $toolCalls;
    }

    /**
     * Execute all tool calls in the collection and returns the results as a ToolResultCollection
     *
     * @param ToolRegistry $toolRegistry
     *
     * @return ToolResultCollection
     */
    public function execute(ToolRegistry $toolRegistry): ToolResultCollection
    {
        $results = [];

        foreach ($this->toolCalls as $toolCall) {
            $result = $toolRegistry->execute(
                toolName: $toolCall->name,
                arguments: $toolCall->arguments,
                toolCallId: $toolCall->id
            );

            if ($result->success) {
                Log::info('AiTool executed successfully', [
                    'tool' => $toolCall->name,
                    'tool_call_id' => $toolCall->id,
                ]);
            } else {
                Log::error('AiTool execution failed', [
                    'tool' => $toolCall->name,
                    'tool_call_id' => $toolCall->id,
                    'error' => $result->error,
                ]);
            }

            $results[] = $result;
        }

        return new ToolResultCollection(...$results);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->toolCalls);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->toolCalls);
    }

    /**
     * Returns true if the collection is empty, false otherwise
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->toolCalls);
    }

    /**
     * Returns true if the collection has entries, false otherwise
     * @return bool
     */
    public function hasItems(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Returns an array of tool calls formatted for sending back to the model
     * @return array
     */
    public function toArray(): array
    {
        return array_map(
            static fn(ToolCall $call) => $call->toArray(),
            $this->toolCalls
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toolCalls;
    }
}
