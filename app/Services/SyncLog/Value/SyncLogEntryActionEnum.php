<?php

namespace App\Services\SyncLog\Value;

enum SyncLogEntryActionEnum: string
{
    case SET = 'set';
    case REMOVE = 'remove';
}
