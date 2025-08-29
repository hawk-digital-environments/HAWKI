<?php
declare(strict_types=1);


namespace App\Services\SyncLog;


use App\Events\SyncLogEvent;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\SyncLog\Db\SyncLogDb;
use App\Services\SyncLog\Handlers\AbstractTransientSyncLogHandler;
use App\Services\SyncLog\Handlers\SyncLogHandlerInterface;
use App\Services\SyncLog\Value\SyncLogPayload;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;

class SyncLogTracker
{
    protected bool $garbageCollected = false;
    
    public function __construct(
        /**
         * @var iterable<SyncLogHandlerInterface>
         */
        #[Tag('syncLog.handler')] protected iterable $handlers,
        protected Dispatcher                         $events,
        protected SyncLogResourceFactory             $syncLogResourceFactory,
        protected SyncLogDb                          $syncLogDb,
    )
    {
    }
    
    public function registerListeners(): void
    {
        foreach ($this->handlers as $handler) {
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
    
    private function track(SyncLogPayload $payload, SyncLogHandlerInterface $handler): void
    {
        $this->removeOldEntries();
        
        foreach ($this->createRecordsForPayload($payload, $handler) as $record) {
            if (!$handler instanceof AbstractTransientSyncLogHandler) {
                $this->syncLogDb->upsert($record);
            }
            
            $this->events->dispatch(
                new SyncLogEvent(
                    $this->syncLogResourceFactory->createForPayloadAndRecord(
                        $record, $payload, $handler
                    )
                )
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
            'updated_at' => now(),
        ];
        
        return $payload->audience->map(static fn(User $user) => new SyncLog(
            [
                ...$data,
                'user_id' => $user->id
            ]
        ));
    }
}
