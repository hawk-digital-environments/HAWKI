<?php

namespace App\Models\Announcements;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Announcement extends Model
{
    use AsSource, Filterable;

    /**
     * The attributes that are allowed to be sorted.
     *
     * @var array
     */
    protected $allowedSorts = [
        'title',
        'type',
        'is_forced',
        'is_global',
        'is_published',
        'starts_at',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'title',
        'view',
        'type',
        'is_forced',
        'is_global',
        'is_published',
        'target_roles',
        'anchor',
        'starts_at',
        'expires_at'
    ];

    protected $attributes = [
        'is_published' => false,
    ];

    protected $casts = [
        'is_forced' => 'boolean',
        'is_global' => 'boolean',
        'is_published' => 'boolean',
        'target_roles' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_user')
                    ->using(AnnouncementUser::class) // use custom pivot model
                    ->withPivot(['seen_at', 'accepted_at'])
                    ->withTimestamps();
    }

    /**
     * Get all translations for the announcement.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AnnouncementTranslation::class);
    }

    /**
     * Get translation for a specific locale.
     */
    public function getTranslation(string $locale): ?AnnouncementTranslation
    {
        return $this->translations()->where('locale', $locale)->first();
    }
}
