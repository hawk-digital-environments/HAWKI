<?php
declare(strict_types=1);


namespace App\Services\Chat\Room\SyncLog;


use App\Events\RoomAiWritingEndedEvent;
use App\Events\RoomAiWritingStartedEvent;
use App\Models\Member;
use App\Services\SyncLog\Handlers\AbstractTransientSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryType;

class RoomAiWritingHandler extends AbstractTransientSyncLogHandler
{
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::ROOM_AI_WRITING;
    }
    
    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            RoomAiWritingStartedEvent::class => function (RoomAiWritingStartedEvent $event) {
                return $this->createSetPayload(
                    [
                        'id' => $event->room->id,
                        'model_id' => $event->model->getId(),
                        'label' => $event->model->getLabel()
                    ],
                    $event->room->members->map(static fn(Member $member) => $member->user),
                    $event->room
                );
            },
            RoomAiWritingEndedEvent::class => function (RoomAiWritingEndedEvent $event) {
                return $this->createRemovePayload(
                    $event->room->members->map(static fn(Member $member) => $member->user),
                    [
                        'id' => $event->room->id,
                        'model_id' => $event->model->getId(),
                        'label' => $event->model->getLabel()
                    ],
                    $event->room,
                );
            },
        ];
    }
}
