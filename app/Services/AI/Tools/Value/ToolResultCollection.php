<?php
declare(strict_types=1);


namespace App\Services\AI\Tools\Value;

/**
 * @extends \IteratorAggregate<int, ToolResult>
 */
readonly class ToolResultCollection implements \IteratorAggregate
{
    /**
     * @var array|ToolResult[]
     */
    private array $toolResults;

    public function __construct(
        ToolResult ...$toolResults
    )
    {
        $this->toolResults = $toolResults;
    }

    /**
     * Returns an array of tool results formatted for sending back to the model
     * Each result is converted to a message format according to OpenAI spec (role="tool")
     * @return array
     */
    public function toMessageFormat(): array
    {
        return array_map(
            static fn(ToolResult $result) => $result->toMessageFormat(),
            $this->toolResults
        );
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->toolResults);
    }
}
