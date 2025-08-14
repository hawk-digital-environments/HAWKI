<?php

namespace App\Models;

use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as OrchidUser;
use Laravel\Sanctum\HasApiTokens;

class User extends OrchidUser
{
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'employeetype',
        'publicKey',
        'avatar_id',
        'bio',
        'isRemoved',
        'permissions',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'permissions',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'password' => 'hashed',
        'permissions' => 'array',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'updated_at',
        'created_at',
    ];

    // Your existing relationships like members, rooms etc.
    public function members()
    {
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'members', 'user_id', 'room_id')
                    ->wherePivot('isRemoved', false);
    }

    public function conversations()
    {
        return $this->hasMany(AiConv::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class, 'username', 'username');
    }

    public function revokProfile(){
        $this->update(['isRemoved'=> 1]);
    }

    /**
     * Scope to get only local users (users with password)
     */
    public function scopeLocalUsers($query)
    {
        return $query->whereNotNull('password');
    }

    /**
     * Scope to get only external users (users without password)
     */
    public function scopeExternalUsers($query)
    {
        return $query->whereNull('password');
    }

    /**
     * Check if this user is a local user
     */
    public function isLocalUser()
    {
        return !is_null($this->password);
    }

    /**
     * Check if this user is an external user
     */
    public function isExternalUser()
    {
        return is_null($this->password);
    }

}