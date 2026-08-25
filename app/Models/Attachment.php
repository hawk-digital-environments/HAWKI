<?php

namespace App\Models;

use App\Services\Chat\Attachment\Events\AttachmentDeleting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $dispatchesEvents = [
        'deleting' => AttachmentDeleting::class,
    ];

    protected static function booted(): void
    {
        // The cascading FK column mirrors the polymorphic pair for AI conversation
        // messages, so attachments created through `$message->attachments()`
        // alone still get removed with their conversation.
        static::creating(function (Attachment $attachment): void {
            if ($attachment->ai_conv_msg_id === null && $attachment->attachable_type === AiConvMsg::class) {
                $attachment->ai_conv_msg_id = $attachment->attachable_id;
            }
        });
    }

    protected $fillable =
    [
        'uuid',
        'name',
        'category',
        'type',
        'mime',
        'user_id',
        'ai_conv_msg_id',
    ];

    /**
     * Let Attachment belong to ANY attachable model (Message, AiConvMsg)
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }


    /**
     * @return BelongsTo<User, $this>
    **/
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
