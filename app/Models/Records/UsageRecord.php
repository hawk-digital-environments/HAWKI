<?php

namespace App\Models\Records;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'prompt_tokens',
        'completion_tokens',
        'model',
        'type',
        'channel',
        'user_agent',
        'format_key',
        'assistant_handle',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
