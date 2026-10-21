<?php

namespace App\Models\Announcements;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

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

    /**
     * @return BelongsToMany<User, $this, AnnouncementUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_user')
                    ->using(AnnouncementUser::class) // use custom pivot model
                    ->withPivot(['seen_at', 'accepted_at'])
                    ->withTimestamps();
    }

}
