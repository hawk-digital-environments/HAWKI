<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConv;
use App\Models\Room;
use App\Services\Chat\Attachment\AttachmentService;
use App\Services\Chat\Message\Interfaces\MessageInterface;


abstract class BaseMessageHandler implements MessageInterface
{
    protected AttachmentService $attachmentService;

    public function __construct(AttachmentService $attachmentService)
    {
        $this->attachmentService = $attachmentService;
    }

    public function assignID(AiConv|Room $room, int $threadId): string {
        $decimalPadding = 3; // Decide how much padding you need. 3 could pad up to 999.

        if ($threadId == 0) {
            // Fetch all messages with whole number IDs (e.g., "0.0", "1.0", etc.)
            $allMessages = $room->messages()
                                ->get()
                                ->filter(function ($message) {
                                    return floor(floatval($message->message_id)) == floatval($message->message_id);
                                });

            if ($allMessages->isNotEmpty()) {
                // Find the message with the highest whole number
                $lastMessage = $allMessages->sortByDesc(function ($message) {
                    return intval($message->message_id);
                })->first();

                // Increment the whole number part
                $newWholeNumber = intval($lastMessage->message_id) + 1;
                $newMessageId = $newWholeNumber . '.000'; // Start with 3 zeros
            } else {
                // If no messages exist, start from 1.000
                $newMessageId = '1.000';
            }
        } else {
            // Fetch all messages that belong to the specified threadId
            $allMessages = $room->messages()
                                ->where('message_id', 'like', "$threadId.%")
                                ->get();

            if ($allMessages->isNotEmpty()) {
                // Find the message with the highest decimal part
                $lastMessage = $allMessages->sortByDesc(function ($message) {
                    return floatval($message->message_id);
                })->first();

                // Increment the decimal part
                $parts = explode('.', $lastMessage->message_id);
                $newDecimal = intval($parts[1]) + 1;
                $newMessageId = $parts[0] . '.' . str_pad($newDecimal, $decimalPadding, '0', STR_PAD_LEFT);
            } else {
                // If no sub-messages exist, start from threadId.001
                $newMessageId = $threadId . '.001';
            }
        }

        return $newMessageId;
    }



}
