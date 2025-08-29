<?php

namespace App\Services\SyncLog\Value;

enum SyncLogEntryTypeEnum: string
{
    case MEMBER = 'member';
    case USER = 'user';
    case ROOM = 'room';
    case ROOM_INVITATION = 'room_invitation';
    case ROOM_AI_WRITING = 'room_ai_writing';
    case MESSAGE = 'message';
    case PRIVATE_USER_DATA = 'private_user_data';
}
