<?php
declare(strict_types=1);


namespace App\Services\Ai\Values\Chunks;


use NeuronAI\Chat\Messages\Stream\Chunks\StreamChunk;

class MaxToolExecutionsChunk extends StreamChunk
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'messageId' => $this->messageId,
            'type' => 'max_tool_executions',
        ];
    }
}
