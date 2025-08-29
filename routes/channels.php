<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('Channel', function ($user) {
//     return true;
// });

Broadcast::channel('Rooms.{roomSlug}', function (User $user, string $roomSlug) {
    $room = Room::where('slug', $roomSlug)->firstOrFail();

    $isMember = $room->isMember($user->id);
    return $isMember;
});

Broadcast::channel('User.{id}', static fn(User $user, string $id) => $user->id === (int)$id);
