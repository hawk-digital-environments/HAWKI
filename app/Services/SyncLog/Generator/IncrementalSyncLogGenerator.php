<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Generator;


use App\Http\Resources\SyncLogEntryResource;
use App\Models\SyncLog;
use App\Services\SyncLog\Db\SyncLogDb;
use App\Services\SyncLog\Handlers\Contract\ConditionalSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\IncrementalSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\SyncLogHandlerInterface;
use App\Services\SyncLog\SyncLogResourceFactory;
use App\Services\SyncLog\Value\IncrementalSyncLogEntryConstraints;
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
     * @return Collection<SyncLogEntryResource>|null
     */
    public function findEntries(IncrementalSyncLogEntryConstraints $constraints): Collection|null
    {
        // We filter out transient handlers, as they do not have persistent log entries.
        $incrementalHandlers = array_filter(
            iterator_to_array($this->handlers, false),
            static fn(SyncLogHandlerInterface $handler) => $handler instanceof IncrementalSyncLogHandlerInterface
        );
        
        // Find a list of types that can provide
        $allowedTypes = [];
        foreach ($incrementalHandlers as $handler) {
            if ($handler instanceof ConditionalSyncLogHandlerInterface && !$handler->canProvide()) {
                continue;
            }
            $allowedTypes[] = $handler->getType()->value;
        }
        
        $constraints = IncrementalSyncLogEntryConstraints::addAllowedTypes(
            constraints: $constraints,
            allowedTypes: $allowedTypes
        );
        
        return $this->syncLogDb->findForIncrementalSync($constraints)
            ?->map(function (SyncLog $syncLog) use ($incrementalHandlers) {
                foreach ($incrementalHandlers as $handler) {
                    if ($handler->getType() === $syncLog->type) {
                        return $this->syncLogResourceFactory->createForRecordAndIncrementalHandler(
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
