<?php

namespace App\Services\SyncLog\Value;

enum SyncLogType: string
{
    case INCREMENTAL = 'incremental';
    case FULL = 'full';
}
