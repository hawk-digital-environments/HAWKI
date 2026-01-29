<?php
declare(strict_types=1);


namespace App\Services\Storage\Value;


enum StorageFileCategory: string
{
    /**
     * Avatars of groups/rooms (a.k.a. multi-user chats)
     */
    case ROOM_AVATAR = 'room_avatars';
    /**
     * Avatars of users
     */
    case PROFILE_AVATAR = 'profile_avatars';
    /**
     * Files shared in group/room chats
     */
    case GROUP = 'group';
    /**
     * Files shared in one-on-one chats (ai-conv)
     */
    case PRIVATE = 'private';
}
