<?php

namespace App\Models\Records;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class UsageRecord extends Model
{
    use AsSource, Filterable;
    
    protected $fillable = [
        'user_id',
        'room_id',
        'type',
        'access_token_id',
        'api_provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cache_read_input_tokens',
        'cache_creation_input_tokens',
        'reasoning_tokens',
        'audio_input_tokens',
        'audio_output_tokens',
        'server_tool_use',
        'status',
    ];
    
    protected $casts = [
        'server_tool_use' => 'array',
    ];
    
    protected $attributes = [
        'cache_read_input_tokens' => 0,
        'cache_creation_input_tokens' => 0,
        'reasoning_tokens' => 0,
        'audio_input_tokens' => 0,
        'audio_output_tokens' => 0,
        'status' => null, // NULL until request completes
    ];
    
    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saving(function (UsageRecord $record) {
            // Auto-calculate total_tokens if not set
            if ($record->isDirty(['prompt_tokens', 'completion_tokens']) || !$record->total_tokens) {
                $record->total_tokens = ($record->prompt_tokens ?? 0) + ($record->completion_tokens ?? 0);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
