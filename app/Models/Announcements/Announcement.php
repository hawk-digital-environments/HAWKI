<?php

namespace App\Models\Announcements;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'view',
        'type',
        'is_forced',
        'is_global',
        'target_users',
        'anchor',
        'starts_at',
        'expires_at'
    ];

    protected $casts = [
        'target_users' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'announcement_user')
                    ->using(AnnouncementUser::class) // use custom pivot model
                    ->withPivot(['seen_at', 'accepted_at'])
                    ->withTimestamps();
    }

}
