<?php
declare(strict_types=1);


namespace App\Services\SyncLog;


use App\Http\Resources\SyncLogEntryResource;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\SyncLog\Handlers\Contract\IncrementalSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\SyncLogHandlerInterface;
use App\Services\SyncLog\Value\SyncLogEntryAction;
use App\Services\SyncLog\Value\SyncLogPayload;

readonly class SyncLogResourceFactory
{
    public function createForPayloadAndRecord(
        SyncLog                 $record,
        SyncLogPayload          $payload,
        SyncLogHandlerInterface $handler
    ): SyncLogEntryResource
    {
        return new SyncLogEntryResource(
            resource: $handler->convertModelToResource($payload->model),
            resourceId: $record->target_id,
            type: $record->type,
            action: $record->action,
            timestamp: $record->updated_at,
            userId: $record->user_id
        );
    }
    
    public function createForRecordAndIncrementalHandler(
        SyncLog                 $record,
        IncrementalSyncLogHandlerInterface $handler
    ): SyncLogEntryResource|null
    {
        $resource = null;
        $reference = null;
        if ($record->action === SyncLogEntryAction::SET) {
            $reference = $handler->findModelById((int)$record->target_id);
            if (!$reference) {
                // If the reference is not found, we return null for SET actions
                // In this case we assume there is some kind of data inconsistency, we ignore silently.
                return null;
            }
        }
        
        if ($reference !== null) {
            $resource = $handler->convertModelToResource($reference);
        }
        
        // If the resource is null, it means the record is for a deleted resource
        // In this case we set resource to true, which is a hack to indicate that the resource is not available,
        // See SyncLogEntryResource::__construct for details
        /** @noinspection ProperNullCoalescingOperatorUsageInspection */
        return new SyncLogEntryResource(
            resource: $resource ?? true,
            resourceId: (int)$record->target_id,
            type: $handler->getType(),
            action: $resource === null ? SyncLogEntryAction::REMOVE : $record->action,
            timestamp: $record->updated_at,
            userId: $record->user_id
        );
    }
    
    public function createForModelAndHandler(
        mixed $model,
        SyncLogHandlerInterface $handler,
        User|null               $user
    ): SyncLogEntryResource
    {
        return new SyncLogEntryResource(
            resource: $handler->convertModelToResource($model),
            resourceId: $handler->getIdOfModel($model),
            type: $handler->getType(),
            action: SyncLogEntryAction::SET,
            timestamp: now(),
            userId: $user?->id ?? -1
        );
    }
}
