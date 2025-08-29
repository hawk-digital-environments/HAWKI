<?php

namespace App\Services\SyncLog\Value;

enum SyncLogTypeEnum: string
{
    case INCREMENTAL = 'incremental';
    case FULL = 'full';
}
