<?php

namespace App\Models;

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

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // public function user()
    // {
    //     $user = User::where('username', $username)->first();
    //     if($user){
    //         return $user;
    //     }
    //     else{
    //         return null;
    //     }
    // }
}
