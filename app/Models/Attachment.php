<?php

namespace App\Models;

use App\Services\Chat\Attachment\Events\AttachmentDeleting;
use Illuminate\Database\Eloquent\Model;

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

    // Let Attachment belong to ANY attachable model (Message, AiConvMsg)
    public function attachable()
    {
        return $this->morphTo();
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

}
