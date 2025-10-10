<?php

namespace App\Models;

use App\Events\InvitationCreatedEvent;
use App\Events\InvitationUpdatedEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        $user = User::where('username', $this->username)->first();
        if($user){
            return $user;
        }
        else{
            return null;
        }
    }
}
