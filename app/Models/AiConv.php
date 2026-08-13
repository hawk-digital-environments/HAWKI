<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AiConv extends Model
{
    protected $fillable = [
        'conv_name',
        'slug',
        'user_id',
        'system_prompt'
    ];

    protected static function booted(): void
    {
        // The slug and owner are server-side concerns; creators (e.g. the
        // JSON:API store action) only provide the name and system prompt.
        static::creating(function (AiConv $conv) {
            $conv->slug ??= Str::slug(Str::random(16));
            $conv->user_id ??= Auth::id();
        });
    }

    /**
     * Define the relationship with User
     * @return BelongsTo<User, $this>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define the relationship with AiConvMsg
     * @return HasMany<AiConvMsg, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiConvMsg::class, 'conv_id');
    }
}
