<?php

namespace App\Models;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as OrchidUser;
use Orchid\Screen\AsSource;

class User extends OrchidUser
{
    use AsSource, Filterable, HasApiTokens, HasFactory, Notifiable;

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
        'auth_type',
        'reset_pw',
        'approval',
        'publicKey',
        'avatar_id',
        'bio',
        'isRemoved',
        'permissions',
        'webauthn_pk',
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
        'approval' => 'boolean',
        'webauthn_pk' => 'boolean',
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
        'approval' => Where::class,
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
        'approval',
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
            ->wherePivot('isMember', true)
            ->wherePivot('isRemoved', false)
            ->withPivot('isRemoved', 'isMember');
    }

    // Rooms including those where user was removed (for showing removal notifications)
    // This includes rooms where isMember=1 and isRemoved=1 (pending removal confirmation)
    public function roomsIncludingRemoved()
    {
        return $this->belongsToMany(Room::class, 'members', 'user_id', 'room_id')
            ->wherePivot('isMember', true)  // Only active members (includes pending removal)
            ->withPivot('isRemoved', 'isMember');
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

    public function hasUnreadInvitations(): bool
    {
        // Check if user has any pending invitations (invitations exist = not accepted)
        return $this->invitations()->exists();
    }

    public function createdPrompts()
    {
        return $this->hasMany(\App\Models\AiAssistantPrompt::class, 'created_by');
    }

    public function revokProfile()
    {
        $this->update(['isRemoved' => 1]);
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
        return ! is_null($this->password);
    }

    /**
     * Check if this user is an external user
     */
    public function isExternalUser()
    {
        return is_null($this->password);
    }

    // SECTION: ANNOUNCEMENTS

    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user')
            ->using(AnnouncementUser::class)
            ->withPivot(['seen_at', 'accepted_at'])
            ->withTimestamps();
    }

    public function unreadAnnouncements()
    {
        $now = now();
        $userRoles = $this->getRoles()->pluck('slug')->toArray();

        return Announcement::query()
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) use ($userRoles) {
                $q->where('is_global', true)
                    ->orWhere(function ($q) use ($userRoles) {
                        // Check if user has any of the target roles
                        foreach ($userRoles as $role) {
                            $q->orWhereJsonContains('target_roles', $role);
                        }
                    });
            })
            ->whereDoesntHave('users', function ($q) {
                $q->where('user_id', $this->id)->whereNotNull('accepted_at');
            })
            ->get();
    }

    public function markAnnouncementAsSeen($announcementId)
    {
        $this->announcements()->syncWithoutDetaching([
            $announcementId => ['seen_at' => now()],
        ]);
    }

    public function markAnnouncementAsAccepted($announcementId)
    {
        $this->announcements()->syncWithoutDetaching([
            $announcementId => ['accepted_at' => now()],
        ]);
    }

    /**
     * Create an admin user for HAWKI with the admin role
     * This overrides Orchid's default createAdmin method
     */
    public static function createAdmin(string $name, string $email, string $password): void
    {
        throw_if(static::where('email', $email)->exists(), 'User already exists');

        // Create the user with basic fields for HAWKI
        $user = static::create([
            'name' => $name,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'auth_type' => 'local',   // HAWKI-specific: local authentication
            'approval' => true,       // HAWKI-specific: approved by default
            'username' => $email,     // HAWKI-specific: use email as username
            'publicKey' => '',        // HAWKI-specific: empty publicKey for admin
            'employeetype' => 'staff', // HAWKI-specific: default to staff for admin
        ]);

        // Find and assign the admin role instead of direct permissions
        $adminRole = \Orchid\Platform\Models\Role::where('slug', 'admin')->first();

        if ($adminRole) {
            $user->addRole($adminRole);
            echo "Admin user created and assigned 'admin' role successfully.\n";
        } else {
            // Fallback: if no admin role exists, create user with all permissions
            $user->update([
                'permissions' => \Orchid\Support\Facades\Dashboard::getAllowAllPermission(),
            ]);
            echo "Admin user created with all permissions (no admin role found).\n";
        }
    }
}
