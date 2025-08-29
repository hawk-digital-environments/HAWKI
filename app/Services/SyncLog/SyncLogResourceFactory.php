<?php
declare(strict_types=1);


namespace App\Services\SyncLog;


use App\Http\Resources\SyncLogEntryResource;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\SyncLog\Handlers\AbstractTransientSyncLogHandler;
use App\Services\SyncLog\Handlers\SyncLogHandlerInterface;
use App\Services\SyncLog\Value\SyncLogEntryActionEnum;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Database\Eloquent\Model;

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
    
    public function createForRecordAndHandler(
        SyncLog                 $record,
        SyncLogHandlerInterface $handler
    ): SyncLogEntryResource|null
    {
        $resource = null;
        $reference = null;
        if ($record->action === SyncLogEntryActionEnum::SET) {
            if ($handler instanceof AbstractTransientSyncLogHandler) {
                // If the handler expects transient data, we do not need to resolve the model from the database
                // In this case the log record already contains all the necessary data
                $reference = $record;
            } else {
                $reference = $handler->findModelById((int)$record->target_id);
                if (!$reference) {
                    // If the reference is not found, we return null for SET actions
                    // In this case we assume there is some kind of data inconsistency, we ignore silently.
                    return null;
                }
            }
        }
        
        if ($reference !== null) {
            $resource = $handler->convertModelToResource($reference);
        }
        
        return new SyncLogEntryResource(
            resource: $resource,
            resourceId: (int)$record->target_id,
            type: $handler->getType(),
            action: $record->action,
            timestamp: $record->updated_at,
            userId: $record->user_id
        );
    }
    
    public function createForModelAndHandler(
        Model                   $model,
        SyncLogHandlerInterface $handler,
        User|null               $user
    ): SyncLogEntryResource
    {
        $resource = $handler->convertModelToResource($model);
        $resourceId = $model->getKey();
        $type = $handler->getType();
        $action = SyncLogEntryActionEnum::SET;
        $timestamp = now();
        $userId = $user?->id ?? -1;
        
        return new SyncLogEntryResource(
            resource: $resource,
            resourceId: $resourceId,
            type: $type,
            action: $action,
            timestamp: $timestamp,
            userId: $userId
        );
    }
}
