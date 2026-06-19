<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Contracts;


use NeuronAI\Chat\Messages\Stream\Chunks\ReasoningChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;

interface StreamingAgentResponseInterface extends AgentResponseInterface
{
    /**
     * @return \Traversable<TextChunk|ReasoningChunk|ToolCallChunk|ToolResultChunk>
     */
    public function chunks(): \Traversable;
}
