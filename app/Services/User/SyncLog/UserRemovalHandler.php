<?php
declare(strict_types=1);


namespace App\Services\User\SyncLog;


use App\Events\UserRemovedEvent;
use App\Services\SyncLog\Db\SyncLogDb;
use App\Services\SyncLog\Handlers\AbstractTransientSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryType;

class UserRemovalHandler extends AbstractTransientSyncLogHandler
{
    public function __construct(
        private readonly SyncLogDb $db
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::USER_REMOVAL;
    }
    
    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        return [
            UserRemovedEvent::class => function (UserRemovedEvent $event) {
                $this->db->deleteAllForUser($event->user);
                return $this->createRemovePayload(
                    $event->user,
                    ['id' => $event->user->id]
                );
            }
        ];
    }
}
