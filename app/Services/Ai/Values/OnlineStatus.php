<?php

namespace App\Services\Ai\Values;

enum OnlineStatus: string
{
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case UNKNOWN = 'unknown';
}
