<?php
declare(strict_types=1);


namespace App\Services\SyncLog;


use App\Events\SyncLogEvent;
use App\Http\Resources\SyncLogEntryResource;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\SyncLog\Db\SyncLogDb;
use App\Services\SyncLog\Handlers\Contract\ConditionalSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\IncrementalSyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\SyncLogHandlerInterface;
use App\Services\SyncLog\Handlers\Contract\UpdatingSyncLogHandlerInterface;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

#[Singleton]
class SyncLogTracker
{
    protected bool $garbageCollected = false;
    protected array $emittedResourceTrackers = [];
    
    public function __construct(
        /**
         * @var iterable<SyncLogHandlerInterface>
         */
        #[Tag('syncLog.handler')] protected iterable $handlers,
        protected Dispatcher                         $events,
        protected SyncLogResourceFactory             $syncLogResourceFactory,
        protected SyncLogDb                          $syncLogDb,
        protected Request $request
    )
    {
    }
    
    public function registerListeners(): void
    {
        foreach ($this->handlers as $handler) {
            // Ignore listeners that do not listen for updates
            if (!$handler instanceof UpdatingSyncLogHandlerInterface) {
                continue;
            }
            
            // Ignore handlers that can not track
            if ($handler instanceof ConditionalSyncLogHandlerInterface && !$handler->canTrack()) {
                continue;
            }
            
            foreach ($handler->listeners() as $eventClass => $callbacks) {
                // If the callbacks are not an array, we wrap them in an array
                if (!is_array($callbacks) || (!is_callable($callbacks) && is_callable($callbacks[0]))) {
                    $callbacks = [$callbacks];
                }
                foreach ($callbacks as $callback) {
                    if (!is_callable($callback)) {
                        continue;
                    }
                    $this->events->listen($eventClass, function (...$args) use ($callback, $handler) {
                        $payload = $callback(...$args);
                        if ($payload instanceof SyncLogPayload) {
                            $this->track($payload, $handler);
                        }
                    });
                }
            }
        }
    }
    
    /**
     * Allows the outside world to run the given callback, and collect all emitted SyncLogEntryResource instances while doing so.
     *
     * @param callable $callback A callback to run while collecting emitted SyncLogEntryResource instances
     * @param SyncLogEntryResource[] $entries The collected SyncLogEntryResource instances will be stored in this array
     * @param callable(SyncLogEntryResource): bool|null $filter An optional filter function to filter the collected entries. The function should return true to include the entry, false to exclude it.
     * @return mixed The return value of the given callback
     */
    public function runWithResourceCollection(callable $callback, array &$entries, callable|null $filter = null): mixed
    {
        $entries = [];
        $tracker = static function (SyncLogEntryResource $entry) use ($filter, &$entries) {
            if ($filter === null || $filter($entry)) {
                $entries[] = $entry;
            }
        };
        $this->emittedResourceTrackers[] = $tracker;
        try {
            return $callback();
        } finally {
            $this->emittedResourceTrackers = array_filter($this->emittedResourceTrackers, static fn($t) => $t !== $tracker);
        }
    }
    
    private function track(SyncLogPayload $payload, SyncLogHandlerInterface $handler): void
    {
        $this->removeOldEntries();
        
        foreach ($this->createRecordsForPayload($payload, $handler) as $record) {
            if ($handler instanceof IncrementalSyncLogHandlerInterface) {
                $this->syncLogDb->upsert($record);
            }
            
            $resource = $this->syncLogResourceFactory->createForPayloadAndRecord(
                $record, $payload, $handler
            );
            
            foreach ($this->emittedResourceTrackers as $tracker) {
                $tracker($resource);
            }
            
            $this->events->dispatch(
                new SyncLogEvent($resource)
            );
        }
    }
    
    private function removeOldEntries(): void
    {
        if ($this->garbageCollected) {
            return;
        }
        $this->garbageCollected = true;
        $this->syncLogDb->deleteOutdated();
    }
    
    /**
     * @param SyncLogPayload $payload
     * @param SyncLogHandlerInterface $handler
     * @return Collection<SyncLog>
     */
    private function createRecordsForPayload(SyncLogPayload $payload, SyncLogHandlerInterface $handler): Collection
    {
        $data = [
            'type' => $handler->getType()->value,
            'action' => $payload->action->value,
            'target_id' => $handler->getIdOfModel($payload->model),
            'room_id' => $payload->room?->id ?? null,
            'updated_at' => now()
        ];
        
        // If the audience is null, we create a single entry with user_id = null
        // This is used for global changes, e.g. model updates, system settings changes, etc.
        if ($payload->audience === null) {
            return collect([new SyncLog([
                ...$data,
                'user_id' => null
            ])]);
        }
        
        // Deduplicate the users in the audience (user.id !== user.id)
        // Also filter out the user.id === 1, because we do not want to push changes for the AI user.
        return $payload->audience
            ->unique('id')
            ->filter(static fn(User $user) => $user->id !== 1)
            ->map(static fn(User $user) => new SyncLog(
                [
                    ...$data,
                    'user_id' => $user->id
                ]
            ));
    }
}
