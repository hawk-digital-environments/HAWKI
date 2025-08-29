<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Generator;


use App\Http\Resources\SyncLogEntryResource;
use App\Services\SyncLog\Handlers\AbstractTransientSyncLogHandler;
use App\Services\SyncLog\Handlers\SyncLogHandlerInterface;
use App\Services\SyncLog\SyncLogResourceFactory;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Collection;

class FullSyncLogGenerator
{
    public function __construct(
        /**
         * @var iterable<SyncLogHandlerInterface>
         */
        #[Tag('syncLog.handler')] protected iterable $handlers,
        protected SyncLogResourceFactory             $syncLogResourceFactory
    )
    {
    }
    
    /**
     * @param SyncLogEntryConstraints $constraints
     * @return Collection<SyncLogEntryResource>
     */
    public function findEntries(SyncLogEntryConstraints $constraints): Collection
    {
        // Ensure the constraints are set for a full sync
        $constraints = new SyncLogEntryConstraints(
            user: $constraints->user,
            lastSync: null,
            offset: $constraints->offset ?? 0,
            limit: $constraints->limit ?? PHP_INT_MAX,
            roomId: $constraints->roomId
        );
        
        $offset = $constraints->offset;
        $remaining = $constraints->limit;
        $entries = collect();
        foreach ($this->handlers as $handler) {
            // Ignore transient handlers, as they do not have persistent log entries.
            if ($handler instanceof AbstractTransientSyncLogHandler) {
                continue;
            }
            
            $count = $handler->findCountForFullSync(new SyncLogEntryConstraints(
                user: $constraints->user,
                lastSync: null,
                offset: 0,
                limit: PHP_INT_MAX,
                roomId: $constraints->roomId
            ));
            
            if ($count <= 0) {
                continue; // No entries found, skip this handler
            }
            
            // If the offset is beyond the count, skip this handler
            if ($offset >= $count) {
                $offset -= $count;
                continue;
            }
            
            $takeFromHandler = min($remaining, $count - $offset);
            $models = $handler->findModelsForFullSync(new SyncLogEntryConstraints(
                user: $constraints->user,
                lastSync: null,
                offset: $offset,
                limit: $takeFromHandler,
                roomId: $constraints->roomId
            ));
            $entries = $entries->merge(
                $models->map(
                    fn($model) => $this->syncLogResourceFactory->createForModelAndHandler($model, $handler, $constraints->user)
                )
            );
            $remaining -= $models->count();
            
            if ($remaining <= 0) {
                break;
            }
        }
        
        return $entries;
    }
}
