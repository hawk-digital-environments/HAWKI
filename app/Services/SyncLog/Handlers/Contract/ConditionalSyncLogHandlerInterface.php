<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers\Contract;


interface ConditionalSyncLogHandlerInterface
{
    /**
     * MUST return true if the handler can track sync logs for the current context (e.g. user permissions, etc.)
     * @return bool
     */
    public function canTrack(): bool;
    
    /**
     * MUST return true if the handler can provide sync logs for the current context (e.g. user permissions, etc.)
     * This is used both for incremental sync and full sync.
     * @return bool
     */
    public function canProvide(): bool;
}
