<?php

namespace App\Models\Announcements;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Models\User;


class AnnouncementUser extends Pivot
{
    protected $table = 'announcement_user';

    // The pivot table has its own auto-increment `id`, unlike the composite-key pivots the
    // Pivot base class assumes. Declaring that lets the row be read and written on its own
    // (consent records are queried per user, not only through the relation).
    public $incrementing = true;

    protected $fillable = [
        'announcement_id', 'user_id',
        'seen_at', 'accepted_at',
        // Which revision of the announcement text was consented to, and in which language.
        'locale', 'content_hash',
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

