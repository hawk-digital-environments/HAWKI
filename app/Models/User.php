<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    
    protected $fillable = [
        'name',
        'email',
        'username',
        'employeetype',
        'publicKey',
        'avatar_id',
        'bio',
    ];


    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function rooms()
    {
        return $this->hasManyThrough(
            Room::class,
            Member::class,
            'user_id', // Foreign key on the members table
            'id',      // Foreign key on the rooms table
            'id',      // Local key on the users table
            'room_id'  // Local key on the members table
        );
    }

    // Define the relationship with AiConv
    public function conversations()
    {
        return $this->hasMany(AiConv::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class, 'username', 'username');
    }
}