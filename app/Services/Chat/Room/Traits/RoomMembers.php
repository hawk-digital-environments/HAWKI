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
        $member = $room->members()->where('user_id', $user->id)->firstOrFail();
        return $this->removeMember($member, $room);
    }

    /**
     * @throws Exception
     */
    public function kick($slug, $username): bool{

        $room = Room::where('slug', $slug)->firstOrFail();
        $user = User::where('username', $username)->firstOrFail();
        $member = $room->members()->where('user_id', $user->id)->firstOrFail();

        if($member->user_id === '1'){
            throw new Exception('You can\'t kick AI Agent.');
        }

        return $this->removeMember($member, $room);
    }

    public function removeMember(Member $member, Room $room): bool
    {
        // Remove the member from the room
        $room->removeMember($member->user_id);

        //Check if All the members have left the room.
        if ($room->members()->count() === 1) {
            $this->delete($room->slug);
        }

        return true;
    }



    public function searchUser(string $query): array
    {
        // Search in the database for users matching the query and is not removed
        $users = User::where('isRemoved', false)
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
        });
    }
}
