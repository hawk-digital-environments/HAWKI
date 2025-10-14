<?php

namespace App\Models;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


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


    public function members()
    {
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'members', 'user_id', 'room_id')
                    ->wherePivot('isRemoved', false);
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

    public function revokProfile(){
        $this->update(['isRemoved'=> 1]);
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

}
