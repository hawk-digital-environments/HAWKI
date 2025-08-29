<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\MessageSentEvent;
use App\Events\MessageUpdateEvent;
use App\Http\Resources\MessageResource;
use App\Models\Member;
use App\Models\Message;
use App\Models\Room;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class MessageHandler extends AbstractSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::MESSAGE;
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (MessageSentEvent|MessageUpdateEvent $event) {
            return $this->createSetPayload(
                $event->message,
                $event->message->room->members->map(fn(Member $member) => $member->user),
                $event->message->room
            );
        };

        return [
            MessageSentEvent::class => $handleSet,
            MessageUpdateEvent::class => $handleSet
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(Model $model): JsonResource
    {
        /** @var Message $model */
        return new MessageResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?Model
    {
        return Message::find($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(Model $model): int
    {
        /** @var Message $model */
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
