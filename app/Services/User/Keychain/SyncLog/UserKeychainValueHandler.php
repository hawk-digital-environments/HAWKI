<?php
declare(strict_types=1);


namespace App\Services\User\Keychain\SyncLog;


use App\Events\UserKeychainValueCreatedEvent;
use App\Events\UserKeychainValueDeletingEvent;
use App\Events\UserKeychainValueUpdatedEvent;
use App\Http\Resources\UserKeychainValueResource;
use App\Models\UserKeychainValue;
use App\Services\SyncLog\Handlers\AbstractSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use App\Services\User\Keychain\UserKeychainDb;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractSyncLogHandler<UserKeychainValue>
 */
class UserKeychainValueHandler extends AbstractSyncLogHandler
{
    public function __construct(
        private readonly UserKeychainDb $db
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::USER_KEYCHAIN_VALUE;
    }
    
    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (UserKeychainValueCreatedEvent|UserKeychainValueUpdatedEvent $event) {
            return $this->createSetPayload(
                $event->value,
                $event->value->user
            );
        };
        
        return [
            UserKeychainValueCreatedEvent::class => $handleSet,
            UserKeychainValueUpdatedEvent::class => $handleSet,
            UserKeychainValueDeletingEvent::class => function (UserKeychainValueDeletingEvent $event) {
                return $this->createRemovePayload(
                    model: $event->value,
                    audience: $event->value->user
                );
            },
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $this->db->getCountForUser($constraints->user);
    }
    
    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        return $this->db->findForSyncLogConstraints($constraints);
    }
    
    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?UserKeychainValue
    {
        return $this->db->findOne($id);
    }
    
    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
        return new UserKeychainValueResource($model);
    }
    
    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
    {
        return $model->id;
    }
}
