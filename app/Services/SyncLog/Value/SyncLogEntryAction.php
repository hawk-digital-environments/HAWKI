<?php

namespace App\Services\SyncLog\Value;

enum SyncLogEntryAction: string
{
    case SET = 'set';
    case REMOVE = 'remove';
}
