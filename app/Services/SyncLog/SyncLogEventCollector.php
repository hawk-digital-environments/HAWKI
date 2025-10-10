<?php
declare(strict_types=1);


namespace App\Services\SyncLog;


use App\Models\User;

class SyncLogEventCollector
{
    public function __construct(
        private SyncLogTracker $tracker
    )
    {
    }
    
    public function collectForUser(User $targetUser, callable $callback, array &$events): mixed
    {
    
    }
}
