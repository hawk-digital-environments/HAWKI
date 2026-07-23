<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConv extends Model
{
    protected $fillable = [
        'conv_name',
        'slug',
        'user_id',
        'system_prompt'
    ];

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
