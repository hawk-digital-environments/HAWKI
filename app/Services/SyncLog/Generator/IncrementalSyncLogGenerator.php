<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Generator;


use App\Http\Resources\SyncLogEntryResource;
use App\Models\SyncLog;
use App\Services\SyncLog\Db\SyncLogDb;
use App\Services\SyncLog\Handlers\AbstractTransientSyncLogHandler;
use App\Services\SyncLog\Handlers\SyncLogHandlerInterface;
use App\Services\SyncLog\SyncLogResourceFactory;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Support\Collection;

class IncrementalSyncLogGenerator
{
    public function __construct(
        /**
         * @var iterable<SyncLogHandlerInterface>
         */
        #[Tag('syncLog.handler')] protected iterable $handlers,
        protected SyncLogDb                          $syncLogDb,
        protected SyncLogResourceFactory             $syncLogResourceFactory
    )
    {
    
    }
    
    /**
     * @param SyncLogEntryConstraints $constraints
     * @return Collection<SyncLogEntryResource>|null
     */
    public function findEntries(SyncLogEntryConstraints $constraints): Collection|null
    {
        // We filter out transient handlers, as they do not have persistent log entries.
        $handlersWithoutTransient = array_filter(
            iterator_to_array($this->handlers, false),
            static fn(SyncLogHandlerInterface $handler) => !($handler instanceof AbstractTransientSyncLogHandler)
        );
        
        return $this->syncLogDb->findForIncrementalSync($constraints)
            ?->map(function (SyncLog $syncLog) use ($handlersWithoutTransient) {
                foreach ($handlersWithoutTransient as $handler) {
                    if ($handler->getType() === $syncLog->type) {
                        return $this->syncLogResourceFactory->createForRecordAndHandler(
                            $syncLog, $handler
                        );
                    }
                }
                // If no handler is found, we return null for this entry.
                return null;
            })->filter(function ($resource) {
                return $resource !== null;
            });
    }
}
