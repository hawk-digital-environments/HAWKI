<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers\Contract;

/**
 * Interface for handlers that want to listen to update events and create sync log entries.
 *
 * @template T
 * @extends SyncLogHandlerInterface<T>
 */
interface UpdatingSyncLogHandlerInterface extends SyncLogHandlerInterface
{
    /**
     * MUST return a list of events and their respective listeners
     * @return array<string, callable|callable[]> The keys are the event names, and the values are the listener methods.
     * The listener methods should be callable and will be invoked when the event is fired.
     * The listener MAY return a {@see SyncLogPayload} that will be used to create the log entries.
     * You can return a single callable or an array of callables for each event.
     */
    public function listeners(): array;
}
