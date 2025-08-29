<?php
declare(strict_types=1);


namespace App\Services\Message;


use App\Services\Message\Value\LegacyMessageIdInfo;
use App\Services\Message\Value\LegacyThreadInfo;

/**
 * This class is a legacy helper to deal with the old message ID and thread ID formats.
 * It provides methods to convert between the old and new formats and retrieve relevant information.
 */
final readonly class LegacyMessageHelper
{
    public function __construct(
        protected MessageDb $messageDb
    )
    {
    }
    
    /**
     * Gets the legacy message ID info for a given ID.
     * The ID can be either the internal database ID (int) or the legacy message ID (string in the format "12.001").
     *
     * @param string|int $id Either the internal ID (int) or the legacy message ID (string) to look up.
     * @return LegacyMessageIdInfo The information about the message ID.
     * @throws \InvalidArgumentException if the message is not found or the ID format is invalid.
     */
    public function getMessageIdInfo(string|int $id): LegacyMessageIdInfo
    {
        $message = null;
        
        if (is_string($id) && str_contains($id, '.')) {
            $message = $this->messageDb->findOneByMessageId($id);
        } elseif (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $message = $this->messageDb->findOneById((int)$id);
        }
        
        if ($message === null) {
            throw new \InvalidArgumentException('Message with ID ' . $id . ' not found');
        }
        
        return new LegacyMessageIdInfo(
            id: $message->id,
            legacyMessageId: $message->message_id
        );
    }
    
    /**
     * The same as {@see self::getThreadInfo()} but uses the legacy "message_id" format.
     * @param string $messageId
     * @return LegacyThreadInfo
     */
    public function getThreadInfoByLegacyMessageId(string $messageId): LegacyThreadInfo
    {
        return $this->getThreadInfo($this->convertMessageIdToThreadId($messageId), false);
    }
    
    /**
     * Retrieves thread information based on the thread ID.
     *
     * @param int $threadId The thread ID to retrieve information for.
     * @param bool $isLegacyThreadId Whether the provided thread ID is a legacy thread ID.
     *                               If true, it will search by the old "message_id" format.
     *                               If false, it will search by the new "thread_id" field in the {@see Message} model.
     * @return LegacyThreadInfo The thread information.
     * @throws \InvalidArgumentException If the thread parent message cannot be found.
     */
    public function getThreadInfo(int $threadId, bool $isLegacyThreadId = false): LegacyThreadInfo
    {
        if ($threadId === 0) {
            return new LegacyThreadInfo(
                threadId: null,
                legacyThreadId: 0
            );
        }
        
        if ($isLegacyThreadId) {
            $message = $this->messageDb->findOneByLegacyThreadId($threadId);
        } else {
            $message = $this->messageDb->findOneById($threadId);
        }
        
        if (!$message) {
            throw new \InvalidArgumentException(sprintf(
                'Failed to find the thread parent message for thread ID %d',
                $threadId
            ));
        }
        
        if ($isLegacyThreadId) {
            $legacyThreadId = $threadId;
        } else {
            $legacyThreadId = $this->convertMessageIdToThreadId($message->message_id);
        }
        
        return new LegacyThreadInfo(
            threadId: $message->id,
            legacyThreadId: $legacyThreadId
        );
    }
    
    protected function convertMessageIdToThreadId(string $messageId): ?int
    {
        $parts = explode('.', $messageId);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid message ID format: ' . $messageId);
        }
        return (int)$parts[0];
    }
}
