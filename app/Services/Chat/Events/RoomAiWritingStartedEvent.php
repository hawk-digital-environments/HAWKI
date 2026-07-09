<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when an AI model begins generating a streamed response in a room.
 *
 * Dispatched by the stream controller immediately before the AI generation starts.
 * Use this event to show a "typing" indicator, log generation starts, or apply
 * rate-limiting checks.
 *
 * Available data (via the parent):
 * - {@see AbstractRoomAiWritingEvent::$room}  — the room receiving the response
 * - {@see AbstractRoomAiWritingEvent::$model} — the AI model doing the writing
 *
 * @see RoomAiWritingEndedEvent fired when generation completes
 */
readonly class RoomAiWritingStartedEvent extends AbstractRoomAiWritingEvent
{
}
