<?php

namespace App\Models;

use App\Services\SyncLog\Value\SyncLogEntryActionEnum;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d H:i:s.u';
    
    protected $fillable = [
        'type',
        'action',
        'target_id',
        'user_id',
        'room_id',
        'updated_at',
    ];
    
    protected $casts = [
        'type' => SyncLogEntryTypeEnum::class,
        'action' => SyncLogEntryActionEnum::class,
        'updated_at' => 'datetime:Y-m-d H:i:s.u',
    ];
}
