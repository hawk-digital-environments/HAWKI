<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Events\RoomAiWritingEndedEvent;
use App\Events\RoomAiWritingStartedEvent;
use App\Models\Member;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;

class RoomAiWritingHandler extends AbstractTransientSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryTypeEnum
    {
        return SyncLogEntryTypeEnum::ROOM_AI_WRITING;
    }
    
    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            RoomAiWritingStartedEvent::class => function (RoomAiWritingStartedEvent $event) {
                return $this->createSetPayload(
                    ['model' => $event->model->getId()],
                    $event->room->members->map(static fn(Member $member) => $member->user),
                    $event->room
                );
            },
            RoomAiWritingEndedEvent::class => function (RoomAiWritingEndedEvent $event) {
                return $this->createRemovePayload(
                    $event->room->members->map(static fn(Member $member) => $member->user),
                    ['model' => $event->model->getId()],
                    $event->room,
                );
            },
        ];
    }
}
