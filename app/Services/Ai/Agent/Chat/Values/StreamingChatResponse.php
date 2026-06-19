<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Chat\Values;


use App\Services\Ai\Agent\Contracts\StreamingAgentResponseInterface;
use App\Services\Ai\Values\Chunks\StreamDoneChunk;
use NeuronAI\Agent\Agent;

readonly class StreamingChatResponse implements StreamingAgentResponseInterface
{
    /**
     * @param ChatRequest $request
     * @param \Closure(): Agent $agentFactory
     */
    public function __construct(
        private ChatRequest $request,
        private \Closure    $agentFactory
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function chunks(): \Traversable
    {
        $handler = ($this->agentFactory)()->stream($this->request->messages);
        $messageId = null;
        foreach ($handler->events() as $chunk) {
            if ($messageId === null && isset($chunk->messageId)) {
                $messageId = $chunk->messageId;
            }
            yield $chunk;
        }
        yield new StreamDoneChunk($handler->getMessage(), $messageId);
    }

}
