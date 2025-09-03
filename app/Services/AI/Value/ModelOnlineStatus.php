<?php

namespace App\Services\AI\Value;

enum ModelOnlineStatus: string
{
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case UNKNOWN = 'unknown';
}
