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

    protected $fillable =
    [
        'uuid',
        'name',
        'category',
        'type',
        'mime',
        'user_id',
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
