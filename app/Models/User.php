<?php

namespace App\Models;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementUser;
use App\Models\Scopes\Generic\ActiveFilterScope;
use App\Models\Scopes\KnownUsersAccessScope;
use App\Policies\UserPolicy;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Announcements\Repositories\UserAnnouncementRepository;
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
use Spatie\Permission\Traits\HasRoles;

#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasContextualScopesTrait;
    use HasRoles;

    protected $guard_name = 'web';

    /**
     * Apply HAWKI account and role-only grant rules to Spatie permission checks.
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if (null !== $guardName && 'web' !== $guardName) {
            return false;
        }

        $permission = $this->filterPermission($permission, 'web');

        return 'web' === $permission->guard_name
            && app(\App\Services\Admin\PermissionService::class)->has($this, $permission->name);
    }

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
        'locale',
        'isRemoved',
        'registration_fingerprint',
    ];

    protected $hidden = [
        'local_password',
    ];

    protected $casts = [
        'isRemoved' => 'boolean',
        'admin_disabled' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (!app()->runningInConsole() || \Illuminate\Support\Facades\Schema::hasTable('model_has_roles')) {
                app(\App\Services\Admin\EmployeeTypeRoleSyncer::class)->sync($user);
            }
        });
    }

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar
            ->setDefaultDisablingGuard(function (#[\Illuminate\Container\Attributes\CurrentUser] ?User $user) {
                return $user
                    ? !\App\Services\Users\UserCondition::cannot($user, 'users.view')
                    : app()->runningInConsole();
            })
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
        // Removal happens outside RoleGuard::mutate(), so drop the memoized eligibility here.
        app(\App\Services\Admin\PermissionService::class)->forget((int) $this->getKey());
    }

    // SECTION: ANNOUNCEMENTS

    /**
     * @return BelongsToMany<Announcement, $this, AnnouncementUser>
     */
    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user')
            ->using(AnnouncementUser::class)
            ->withPivot(['seen_at', 'accepted_at', 'locale', 'content_hash'])
            ->withTimestamps();
    }


    /**
     * @return Collection<int, Announcement>
     */
    public function unreadAnnouncements(): Collection
    {
        return app(UserAnnouncementRepository::class)->findUnreadForUser($this);
    }

    public function markAnnouncementAsSeen($announcementId): void
    {
        $this->announcements()->syncWithoutDetaching([
            $announcementId => ['seen_at' => now()],
        ]);
    }

    public function markAnnouncementAsAccepted($announcementId): void
    {
        $announcement = Announcement::query()->findOrFail($announcementId);
        if ($announcement->type === 'policy') {
            app(RegistrationPolicyService::class)->acceptAnnouncement($this, $announcement);
            return;
        }

        $this->announcements()->syncWithoutDetaching([
            $announcementId => ['accepted_at' => now()],
        ]);
    }

}
