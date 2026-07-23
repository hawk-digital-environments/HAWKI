<?php

namespace App\Models;

use App\Services\Chat\Events\InvitationCreatedEvent;
use App\Services\Chat\Events\InvitationUpdatedEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = [
        'room_id',
        'username',
        'role',
        'iv',
        'tag',
        'invitation'
    ];

    protected $dispatchesEvents = [
        'created' => InvitationCreatedEvent::class,
        'updated' => InvitationUpdatedEvent::class
    ];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
