<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMemberRemovedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $roomSlug;
    public string $username;
    public string $roomName;

    /**
     * Create a new event instance.
     */
    public function __construct(string $roomSlug, string $username, string $roomName)
    {
        $this->roomSlug = $roomSlug;
        $this->username = $username;
        $this->roomName = $roomName;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('User.' . $this->username),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'room_slug' => $this->roomSlug,
            'room_name' => $this->roomName,
        ];
    }
}
