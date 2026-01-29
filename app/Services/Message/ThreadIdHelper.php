<?php
declare(strict_types=1);


namespace App\Services\Message;


use App\Models\Room;

final readonly class ThreadIdHelper
{
    public function __construct(
        protected MessageDb $messageDb
    )
    {
    }
    
    /**
     * Converts the thread index (originally the first part of the message_id in format: "12.001": 12)
     * to the internal thread ID (the database ID of the parent message).
     * @param Room $room
     * @param int $threadIndex
     * @return int
     */
    public function getThreadIdForRoomAndThreadIndex(Room $room, int $threadIndex): int|null
    {
        if ($threadIndex === 0) {
            return null;
        }
        
        $threadParentMessage = $room->messages()
            ->where('message_id', $threadIndex . '.000')
            ->firstOrFail();
        return $threadParentMessage->id;
    }
    
    /**
     * Converts a message ID in the format "12.001" to legacy thread index (12).
     * @param string $messageId
     * @return int|null Returns null if the format is invalid.
     * @throws \InvalidArgumentException if the message ID format is invalid.
     */
    public function convertMessageIdToThreadId(string $messageId): ?int
    {
        $parts = explode('.', $messageId);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid message ID format: ' . $messageId);
        }
        return (int)$parts[0];
    }
}
