<?php


namespace App\Services\Chat\Room\Traits;

use App\Models\ExtApp;
use App\Models\Member;
use App\Models\Room;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;


trait RoomMembers
{
    /**
     * Changes the role of an existing member of the room.
     * New members can only join through invitations, because they need the encrypted room key.
     * @throws AuthorizationException if the user tries to change their own role or the role of HAWKI
     * @throws ModelNotFoundException if the user is not an active member of the room
     */
    public function changeRole(string $slug, string $username, string $role): void
    {
        $room = $this->roomRepository->findOneBySlugOrFail($slug);
        $user = $this->userRepository->findOneByUsername($username);

        if (!$user || !$room->isMember($user->id)) {
            throw (new ModelNotFoundException())->setModel(Member::class);
        }

        // Admins can't change their own role, so a room can never lose its last admin this way
        if ($user->id === Auth::id()) {
            throw new AuthorizationException("You can't change your own role.");
        }

        if ($user->id === $this->userRepository->findHawki()->id) {
            throw new AuthorizationException("The role of HAWKI can't be changed.");
        }

        // The user is already a member, so this only updates the role
        $room->addMember($user->id, $role);
    }


    /**
     * @throws AuthorizationException if the user is the last admin of the room
     */
    public function leave($slug): bool
    {
        /** @var Room $room */
        $room = $this->roomRepository->findOneBySlugOrFail($slug);
        $user = Auth::user();
        /** @var Member $member */
        $member = $room->members()->where('user_id', $user->id)->firstOrFail();

        // The last admin can't leave, otherwise nobody could manage the room anymore; they can only delete it
        if ($member->hasRole(Member::ROLE_ADMIN)
            && $this->roomRepository->countMembersWithRole($room, Member::ROLE_ADMIN) === 1) {
            throw new AuthorizationException('You are the last admin of this room. Make another member an admin first, or delete the room.');
        }

        return $this->removeMember($member, $room);
    }

    /**
     * @throws Exception
     */
    public function kick($slug, $username): bool
    {

        /** @var Room $room */
        $room = $this->roomRepository->findOneBySlugOrFail($slug);
        $user = $this->userRepository->findOneByUsername($username);
        if (!$user) {
            throw (new ModelNotFoundException())->setModel(User::class);
        }
        /** @var Member $member */
        $member = $room->members()->where('user_id', $user->id)->firstOrFail();

        if ($member->user_id === $this->userRepository->findHawki()->id) {
            throw new Exception('You can\'t kick AI Agent.');
        }

        // Leaving goes through leave(), which protects the last admin
        if ($member->user_id === Auth::id()) {
            throw new AuthorizationException("You can't remove yourself from the room, leave it instead.");
        }

        return $this->removeMember($member, $room);
    }

    public function removeMember(Member $member, Room $room): bool
    {
        // Resolve the user before removing: afterwards the access scopes may hide the removed user
        $user = $member->user;

        // Remove the member from the room
        if ($room->removeMember($member->user_id)) {
            // Drop pending invitations, so a removed member can't rejoin by accepting an old invitation
            $this->invitationRepository->deleteAllForUserInRoom($room, $user);
        }
        return true;
    }


    public function searchUser(string $query): array
    {
        // Search in the database for users matching the query and is not removed
        $users = User::where('isRemoved', false)
            ->withoutGlobalScopes()
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->where('users.employeetype', '!=', ExtApp::EMPLOYEE_TYPE_APP)
            ->take(5)
            ->get();

        // REF-> SEARCH_FILTER
        return $users->map(function ($user) {
            return [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'publicKey' => $user->publicKey
            ];
        })->toArray();
    }
}
