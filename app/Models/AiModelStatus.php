<?php

namespace App\Models;

use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Database\Eloquent\Model;

class AiModelStatus extends Model
{
    protected $primaryKey = 'model_id';   // use model_id instead of id
    public $incrementing = false;         // it's not auto-incrementing
    protected $keyType = 'string';        // model_id is a string

    protected $fillable = [
        'model_id',
        'status',
    ];

    protected $casts = [
        'status' => ModelOnlineStatus::class
    ];
}
