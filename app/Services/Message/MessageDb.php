<?php
declare(strict_types=1);


namespace App\Services\Message;


use App\Models\Message;

class MessageDb
{
    /**
     * Finds a single message by its ID.
     * @param int $id
     * @return Message|null
     */
    public function findOneById(int $id): ?Message
    {
        return Message::query()->find($id);
    }
    
    /**
     * Finds a single message by its message ID.
     * @param string $messageId
     * @return Message|null
     */
    public function findOneByMessageId(string $messageId): ?Message
    {
        return Message::query()->where('message_id', $messageId)->first();
    }
    
    /**
     * Finds a single message by its legacy thread ID.
     * The legacy thread ID is the first part of the message_id, which is expected to be in the format "threadId.threadMessageId".
     * This method assumes that the message_id ends with ".000" for the main thread message.
     * @param int $id
     * @return Message|null
     * @deprecated This logic was flawed, as only 1000 messages can be in a thread, otherwise we will create a overflow error.
     */
    public function findOneByLegacyThreadId(int $id): ?Message
    {
        $message_id = $id . '.000';
        return Message::query()->where('message_id', $message_id)->first();
    }
}
