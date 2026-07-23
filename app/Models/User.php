<?php

namespace App\Models;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementUser;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Models\Scopes\KnownUsersAccessScope;
use App\Policies\UserPolicy;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use App\Services\Users\Events\UserCreatedEvent;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasContextualScopesTrait;

    protected $dispatchesEvents = [
        'created' => UserCreatedEvent::class
    ];

    protected $fillable = [
        'name',
        'email',
        'username',
        'employeetype',
        'publicKey',
        'avatar_id',
        'bio',
        'isRemoved'
    ];

    protected $casts = [
        'isRemoved' => 'boolean',
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar
            ->addScope('access', new KnownUsersAccessScope())
            ->addScope('active', new ActiveFilterScope('isRemoved', '0'));
    }

    /**
     * @return User|HasMany<Member, $this>
     */
    public function members()
    {
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }

    /**
     * @return BelongsToMany<Room, $this>
     */
    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'members', 'user_id', 'room_id')
            ->wherePivot('isRemoved', false);
    }

    /**
     * Define the relationship with AiConv
     * @return HasMany<AiConv, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AiConv::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'username', 'username');
    }

    public function revokProfile(): void
    {
        $this->update(['isRemoved' => 1]);
    }


    // SECTION: ANNOUNCEMENTS

    /**
     * @return BelongsToMany<Announcement, $this, AnnouncementUser>
     */
    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user')
            ->using(AnnouncementUser::class)
            ->withPivot(['seen_at', 'accepted_at'])
            ->withTimestamps();
    }


    /**
     * @return Collection<int, Announcement>
     */
    public function unreadAnnouncements(): Collection
    {
        $now = now();

        return Announcement::query()
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->where('is_global', true)
                    ->orWhereJsonContains('target_users', $this->id);
            })
            ->whereDoesntHave('users', function ($q) {
                $q->where('user_id', $this->id)->whereNotNull('accepted_at');
            })
            ->get();
    }

    public function markAnnouncementAsSeen($announcementId): void
    {
        $this->announcements()->syncWithoutDetaching([
            $announcementId => ['seen_at' => now()],
        ]);
    }

    public function markAnnouncementAsAccepted($announcementId): void
    {
        $this->announcements()->syncWithoutDetaching([
            $announcementId => ['accepted_at' => now()],
        ]);
    }

}
