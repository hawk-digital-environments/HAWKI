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
    private $slug; // Store room_id separately

    public function __construct(array $data)
    {
        $this->slug = $data['data']['slug']; // Extract room_id before compression
        $this->data = $data;
    }

    public function broadcastOn(): array {
        try {
            return [
                new PrivateChannel('Rooms.' . $this->slug),
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
