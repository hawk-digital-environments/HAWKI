<?php

namespace App\Models\Announcements;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Models\User;


class AnnouncementUser extends Pivot
{
    protected $table = 'announcement_user';

    protected $fillable = [
        'announcement_id', 'user_id',
        'seen_at', 'accepted_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

