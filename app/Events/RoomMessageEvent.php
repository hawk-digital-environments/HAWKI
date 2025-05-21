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
    private $roomId; // Store room_id separately

    public function __construct(array $data)
    {
        $this->roomId = $data['messageData']['room_id']; // Extract room_id before compression
        $this->data = base64_encode(gzencode(json_encode($data), 9)); // ✅ Use gzencode instead of gzcompress
    }

    public function broadcastOn(): array {
        try {
            $slug = Room::findOrFail($this->roomId)->slug; // Use extracted room_id
            return [
                new PrivateChannel('Rooms.' . $slug),
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
