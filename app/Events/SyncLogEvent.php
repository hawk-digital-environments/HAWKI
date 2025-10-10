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
    
    private int|null $userId;
    private array $payload;
    
    public function __construct(
        SyncLogEntryResource $resource
    )
    {
        $this->userId = $resource->getUserId();
        $this->payload = $resource->toArray();
    }
    
    public function broadcastWith(): array
    {
        return $this->payload;
    }
    
    public function broadcastOn(): PrivateChannel
    {
        if ($this->userId === null) {
            return new PrivateChannel('AllUsers');
        }
        
        return new PrivateChannel('User.' . $this->userId);
    }
}
