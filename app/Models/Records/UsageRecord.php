<?php

namespace App\Models\Records;

use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'prompt_tokens',
        'completion_tokens',
        'model',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
