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

    /**
     * Mirrors the polymorphic `attachable_id` onto the concrete `ai_conv_msg_id`
     * foreign key column.
     *
     * Why this is needed: `attachable_id` points at an `AiConvMsg` (private chat)
     * or a `Message` (group chat room), depending on `attachable_type`. A column
     * with two possible parent tables cannot carry a foreign key, so the database
     * has no way to clean up attachments on its own. `ai_conv_msg_id` is that
     * missing foreign key - it is filled for private-chat attachments only and
     * carries `ON DELETE CASCADE` onto `ai_conv_msgs` (added in the
     * `2026_08_24_120000_add_ai_conv_msg_foreign_key_to_attachments` migration).
     *
     * When it triggers: on every save of an attachment whose `attachable_type` is
     * `AiConvMsg`. In practice that is `$message->attachments()->create(...)` in
     * `AttachmentRepository::assignToMessage()`, but keeping it on the model means
     * any other write through the polymorphic relation keeps the cascade intact
     * instead of silently losing it.
     *
     * Group chats: `Message` attachments keep `ai_conv_msg_id` at `null`, so this
     * hook is a no-op for them. Room attachments are removed in the application
     * layer instead (`Room::deleteRoom()` -> `GroupMessageHandler::delete()`); a
     * symmetric `message_id` foreign key for rooms is out of scope here.
     */
    protected static function booted(): void
    {
        static::saving(static function (Attachment $attachment): void {
            if (AiConvMsg::class === $attachment->attachable_type) {
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
