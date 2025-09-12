<?php

namespace App\Models;

use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Database\Eloquent\Model;

class AiModelStatus extends Model
{
    protected $fillable = [
        'model_id',
        'status',
    ];
    
    protected $casts = [
        'status' => ModelOnlineStatus::class
    ];
}
