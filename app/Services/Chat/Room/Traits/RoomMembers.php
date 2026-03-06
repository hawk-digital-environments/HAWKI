<?php


namespace App\Services\Chat\Room\Traits;

use App\Models\Room;
use App\Models\User;
use App\Models\Member;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;


trait RoomMembers{
    /**
     * @throws Exception
     */
    public function add(string $slug, string $data): array
    {
        try{
            $room = Room::where('slug', $slug)->firstOrFail();
            if(!$room->isMember(Auth::id())){
                throw new AuthorizationException();
            }

            $user = User::where('username', $data['username'])->firstOrFail();
            $room->addMember($user->id, $data['role']);
            return $room->members;
        }
        catch (Exception $e){
            throw new Exception('Failed to add new member:' . $e->getMessage());
        }

    }


    public function leave($slug): bool{
        $room = Room::where('slug', $slug)->firstOrFail();
        $user = Auth::user();
        
        // Find member directly in Member model (not filtered by isMember)
        $member = \App\Models\Member::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->where('isMember', true)  // User must be a member
            ->firstOrFail();
        
        return $this->removeMember($member, $room);
    }

    /**
     * @throws Exception
     */
    public function kick($slug, $username): bool{

        $room = Room::where('slug', $slug)->firstOrFail();
        $user = User::where('username', $username)->firstOrFail();

        if($user->id === '1'){
            throw new Exception('You can\'t kick AI Agent.');
        }

        // Find the member record and mark as removed (but still member for UI feedback)
        // Status: isMember=1, isRemoved=1 (user sees room with badge)
        $member = \App\Models\Member::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $member->update([
            'isMember' => true,  // Still member (for UI feedback)
            'isRemoved' => true  // But marked as removed
        ]);

        // Broadcast event to removed user
        event(new \App\Events\RoomMemberRemovedEvent($slug, $user->username, $room->room_name));

        return true;
    }

    public function removeMember(Member $member, Room $room): bool
    {
        \Log::info('removeMember called', [
            'member_id' => $member->id,
            'user_id' => $member->user_id,
            'room_id' => $room->id,
            'current_isMember' => $member->isMember,
            'current_isRemoved' => $member->isRemoved
        ]);
        
        // Actually remove the member from the room
        // Status: isMember=0, isRemoved=1 (user gone)
        $member->update([
            'isMember' => false,
            'isRemoved' => true
        ]);
        
        \Log::info('removeMember updated', [
            'member_id' => $member->id,
            'new_isMember' => $member->fresh()->isMember,
            'new_isRemoved' => $member->fresh()->isRemoved
        ]);

        // Check if all members have left the room
        if ($room->members()->count() === 1) {
            $this->delete($room->slug);
        }

        return true;
    }

    public function searchUser(string $query): array
    {
        // Search in the database for users matching the query and is not removed
        // ONLY show users WITH publicKey (required for E2EE invitations)
        $users = User::where('isRemoved', false)
            ->whereNotNull('publicKey') // Only users with publicKey can be invited
            ->where('publicKey', '!=', '') // Also filter out empty strings
            ->where(function($queryBuilder) use ($query) {
                $queryBuilder->where('name', 'like', "%{$query}%")
                            ->orWhere('username', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
            })
            ->take(5)
            ->get();

            // REF-> SEARCH_FILTER
        return $users->map(function($user){
            return [
                'name'      => $user->name,
                'username'  => $user->username,
                'email'     => $user->email,
                'publicKey'=> $user->publicKey
            ];
        })->toArray();
    }
}
