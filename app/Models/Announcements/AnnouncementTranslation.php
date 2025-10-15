<?php

namespace App\Models\Announcements;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementTranslation extends Model
{
    protected $fillable = [
        'announcement_id',
        'locale',
        'content',
    ];

    /**
     * Get the announcement that owns the translation.
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
