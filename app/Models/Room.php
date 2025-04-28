<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_name', 
        'room_icon',
        'room_description',
        'system_prompt',
        'slug'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($room) {
            $room->slug = Str::slug($room->room_name) . '-' . Str::random(6);
        });
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('message_id');
    }



    /**
     * The users that are members of the room.
     */
    public function membersAll()
    {
        return $this->hasMany(Member::class);
    }
    public function members()
    {
        return $this->hasMany(Member::class)->where('isRemoved', false);
    }
    public function isMember($userId)
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->exists();
    }

    public function oldMembers()
    {
        return $this->hasMany(Member::class)->where('isRemoved', true);
    }
    public function isOldMember($userId)
    {
        return $this->oldMembers()
                    ->where('user_id', $userId)
                    ->exists();
    }
    
    public function addMember($userId, $role)
    {
        if($this->isMember($userId)){
            $member = $this->members()->where('user_id', $userId)->first();
            if(!$member->hasRole($role)){
                $member->updateRole($role);
            }
        }
        else{
            if($this->isOldMember($userId)){

                // if an old membership exists for the user
                // reactivate the old membership.
                $member = $this->membersAll()->where('user_id', $userId)->first();
                $member->recreateMembership();

                if(!$member->hasRole($role)){
                    $member->updateRole($role);
                }

            }
            else{
                // create new member for the room
                $this->members()->create([
                    'user_id' => $userId,
                    'role' => $role,
                ]);
            }

        }


    }

    public function removeMember($userId)
    {
        if($this->isMember($userId)){
            
            // Attempt to delete the member from the room based on user ID
            return $this->members()
                        ->where('user_id', $userId)
                        ->firstOrFail()
                        ->revokeMembership();
        }
    }


    

    public function hasRole($userId, $role)
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->where('role', $role)
                    ->exists();
    }


    public function changeName($newName)
    {
        $this->update(['room_name' => $newName]);
    }



    public function hasUnreadMessagesFor($member){
        // get the last 100 messages.
        $msgs = $this->messages()
        ->orderBy('updated_at', 'desc')
        ->take(100)
        ->get();

        // iterate the messages in reverse order from the newest to the oldest.
        for ($i = count($msgs) - 1; $i >= 0; $i--) {
            if(!$msgs[$i]->isReadBy($member)){
                return true;
            }
        }
        return false;
    }
}
