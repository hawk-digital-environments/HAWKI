<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomInvitationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    private $username;

    public function __construct(array $data, string $username)
    {
        $this->data = $data;
        $this->username = $username;
    }

    public function broadcastOn(): array {
        return [
            new PrivateChannel('User.' . $this->username),
        ];
    }
}
