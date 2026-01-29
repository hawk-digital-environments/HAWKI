<?php

namespace App\Services\SyncLog\Value;

enum SyncLogEntryType: string
{
    case AI_MODEL = 'ai_model';
    case SYSTEM_PROMPT = 'system_prompt';
    case MEMBER = 'member';
    case USER = 'user';
    case USER_REMOVAL = 'user_removal';
    case USER_KEYCHAIN_VALUE = 'user_keychain_value';
    case ROOM = 'room';
    case ROOM_INVITATION = 'room_invitation';
    case ROOM_AI_WRITING = 'room_ai_writing';
    case ROOM_MESSAGE = 'room_message';
}
