<?php

use App\Models\User;
use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('Channel', function ($user) {
//     return true;
// });

// User-specific channel for invitations and notifications
Broadcast::channel('User.{username}', function (User $user, string $username) {
    return $user->username === $username;
});

Broadcast::channel('Rooms.{roomSlug}', function (User $user, string $roomSlug) {
    $room = Room::where('slug', $roomSlug)->firstOrFail();

    $isMember = $room->isMember($user->id);    
    return $isMember;
});