<?php
declare(strict_types=1);


namespace App\Events;


use App\Http\Resources\SyncLogEntryResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

readonly class SyncLogEvent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(protected SyncLogEntryResource $entry)
    {
    }
    
    public function broadcastQueue(): string
    {
        return 'sync';
    }

    public function broadcastWith(): array
    {
        return $this->entry->toArray(request());
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('User.' . $this->entry->getUserId());
    }
}
