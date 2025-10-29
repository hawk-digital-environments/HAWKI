<?php
declare(strict_types=1);


namespace App\Services\Chat\Room\SyncLog;


use App\Events\AttachmentAssignedToMessageEvent;
use App\Events\AttachmentRemovedFromMessageEvent;
use App\Events\MessageSentEvent;
use App\Events\MessageUpdatedEvent;
use App\Http\Resources\RoomMessageResource;
use App\Models\Member;
use App\Models\Message;
use App\Models\Room;
use App\Services\SyncLog\Handlers\AbstractSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractSyncLogHandler<Message>
 */
class RoomMessageHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::ROOM_MESSAGE;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (
            MessageSentEvent|MessageUpdatedEvent|AttachmentRemovedFromMessageEvent|AttachmentAssignedToMessageEvent $event
        ) {
            return $this->createSetPayload(
                $event->message,
                $event->message->room->members->map(fn(Member $member) => $member->user),
                $event->message->room
            );
        };

        return [
            MessageSentEvent::class => $handleSet,
            MessageUpdatedEvent::class => $handleSet,
            AttachmentRemovedFromMessageEvent::class => $handleSet,
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
        return new RoomMessageResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Message
    {
        return Message::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
    {
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $this->getRoomsRelationByConstraints($constraints)
            ->withCount('messages')
            ->get()
            ->sum('messages_count');
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        $messages = collect();
        $currentOffset = $constraints->offset;
        $remaining = $constraints->limit;

        $this->getRoomsRelationByConstraints($constraints)
            ->orderBy('rooms.id')
            ->chunk(100, function ($rooms) use (&$messages, &$currentOffset, &$remaining) {
                if ($remaining <= 0) {
                    return false;
                }

                foreach ($rooms as $room) {
                    /** @var Room $room */
                    $roomMessageCount = $room->messages()->count();

                    if ($currentOffset >= $roomMessageCount) {
                        $currentOffset -= $roomMessageCount;
                        continue;
                    }

                    $takeCount = min($remaining, $roomMessageCount - $currentOffset);
                    $roomMessages = $room->messages()
                        ->orderBy('id')
                        ->skip($currentOffset)
                        ->take($takeCount)
                        ->get();

                    $messages = $messages->merge($roomMessages);
                    $remaining -= $roomMessages->count();
                    $currentOffset = 0;

                    if ($remaining <= 0) {
                        return false;
                    }
                }

                return true;
            });

        return $messages;
    }
}
