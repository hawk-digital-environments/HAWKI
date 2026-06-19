<?php
declare(strict_types=1);


namespace App\Services\Ai\Values\Chunks;


use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Chunks\StreamChunk;

class StreamDoneChunk extends StreamChunk
{
    public function __construct(private Message $message, ?string $messageId = null)
    {
        parent::__construct($messageId);
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'messageId' => $this->messageId,
            'done' => true,
        ];
    }
}
