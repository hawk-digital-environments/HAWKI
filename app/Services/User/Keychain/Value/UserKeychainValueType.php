<?php

namespace App\Services\User\Keychain\Value;

enum UserKeychainValueType: string
{
    case PRIVATE_KEY = 'private_key';
    case PUBLIC_KEY = 'public_key';
    case ROOM = 'room_key';
    case ROOM_AI = 'room_ai';
    case ROOM_AI_LEGACY = 'room_ai_legacy';
    case AI_CONV = 'ai_conv';
}
