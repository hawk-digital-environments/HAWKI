<?php

namespace App\Events;

use App\Models\Room;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data; 

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastOn(): array {
        try {
            $slug = Room::findOrFail($this->data['messageData']['room_id'])->slug;
            return [
                new PrivateChannel('Rooms.' . $slug), // ensure this matches your channel definition
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

}