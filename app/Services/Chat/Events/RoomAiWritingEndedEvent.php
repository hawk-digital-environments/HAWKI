<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when an AI model finishes generating a streamed response in a room.
 *
 * Dispatched by the stream controller after the AI generation phase ends,
 * regardless of whether the response completed successfully or was aborted.
 * Use this event to clear "typing" indicators, log generation completion,
 * or trigger post-response processing.
 *
 * Available data (via the parent):
 * - {@see AbstractRoomAiWritingEvent::$room}  — the room that received the response
 * - {@see AbstractRoomAiWritingEvent::$model} — the AI model that was writing
 *
 * @see RoomAiWritingStartedEvent fired when generation begins
 */
readonly class RoomAiWritingEndedEvent extends AbstractRoomAiWritingEvent
{
}
