<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily usage aggregation per user, model, and provider.
 * 
 * This model stores aggregated usage statistics from usage_records table.
 * One row per user × date × model × provider combination.
 * 
 * Similar to LiteLLM's DailyUserSpend but with HAWKI-specific extensions.
 */
class UsageUsersDaily extends Model
{
    protected $table = 'usage_users_daily';
    
    protected $fillable = [
        'user_id',
        'date',
        'api_provider',
        'model',
        'api_requests',
        'successful_requests',
        'failed_requests',
        'cancelled_requests',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cache_read_input_tokens',
        'cache_creation_input_tokens',
        'reasoning_tokens',
        'audio_input_tokens',
        'audio_output_tokens',
        'server_tool_use',
        'spend',
        'input_token_price_per_1k',
        'cache_read_price_per_1k',
        'cache_write_price_per_1k',
        'output_token_price_per_1k',
        'reasoning_token_price_per_1k',
    ];
    
    protected $casts = [
        'date' => 'date',
        'server_tool_use' => 'array',
        'spend' => 'decimal:4',
        'input_token_price_per_1k' => 'decimal:6',
        'cache_read_price_per_1k' => 'decimal:6',
        'cache_write_price_per_1k' => 'decimal:6',
        'output_token_price_per_1k' => 'decimal:6',
        'reasoning_token_price_per_1k' => 'decimal:6',
    ];
    
    /**
     * Get the user that owns this usage record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
