<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    protected $fillable = [
        'level',
        'channel',
        'message',
        'context',
        'stack_trace',
        'remote_addr',
        'user_agent',
        'user_id',
        'logged_at',
    ];

    protected $casts = [
        'context' => 'array',
        'logged_at' => 'datetime',
    ];

    /**
     * Relationship to User model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by log level
     */
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for filtering by channel
     */
    public function scopeChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }
}
