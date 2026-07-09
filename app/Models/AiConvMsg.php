<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;


class AiConvMsg extends Model
{
    protected $fillable = [
        'conv_id',
        'user_id',
        'message_role',
        'message_id',
        'model',
        'iv',
        'tag',
        'content',
        'completion',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Define the relationship with AiConv
     * @return BelongsTo<AiConv, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConv::class, 'conv_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
