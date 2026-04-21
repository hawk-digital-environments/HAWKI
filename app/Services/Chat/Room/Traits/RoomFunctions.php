<?php

namespace App\Services\Chat\Room\Traits;

use App\Events\RoomCreatedEvent;
use App\Http\Resources\Legacy\RoomResource;
use App\Models\Member;
use App\Models\Room;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait RoomFunctions
{

    public function create(array $data): Room
    {
        // Create the room with name and description
        $room = Room::create([
            'room_name' => $data['room_name'],
        ]);

        // Because we need the room to have members (that will act as audience for sync logs)
        // we first create the room and members without triggering the events,
        // only after that we dispatch the events
        $deferred = $room->runWithDeferredMemberEvents(function () use ($room) {
            // Add AI as assistant
            $room->addMember(1, Member::ROLE_ASSISTANT);
            // Add the creator as admin
            $room->addMember(Auth::id(), Member::ROLE_ADMIN);
        });

        RoomCreatedEvent::dispatch($room);
        $deferred();

        return $room;
    }

    public function load($slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if (!$room->isMember(Auth::id())) {
            throw new AuthorizationException();
        }

        /** @var Member $membership */
        $membership = $room->members()->where('user_id', Auth::id())->firstOrFail();
        $membership->updateLastRead();

        $role = $membership->role;
        /** @var RoomResource $resource */
        $resource = $room->toResource(RoomResource::class);
        return $resource->setRole($role)->resolve();
    }

    public function update(array $data, string $slug)
    {

        $room = Room::where('slug', $slug)->firstOrFail();

        try {
            if (!empty($data['image'])) {
                $this->assignAvatar($data['image'], $slug);
            }
            if (!empty($data['system_prompt'])) {
                $room->update(['system_prompt' => $data['system_prompt']]);
            }
            if (!empty($data['description'])) {
                $room->update(['room_description' => $data['description']]);
            }
            if (!empty($data['name'])) {
                $room->update(['room_name' => $data['name']]);
            }
            return true;
        } catch (Exception $e) {
            Log::error("Failed to update Room Information. Error: $e");
            return false;
        }
    }


    public function assignAvatar(UploadedFile $image, string $slug): array
    {
        $avatarStorage = app(AvatarStorageService::class);
        try {
            $room = Room::where('slug', $slug)->firstOrFail();

            $newAvatar = $avatarStorage->store(
                file: FileReference::fromUploadedFile($image),
                category: StoredFileCategory::ROOM_AVATAR
            );

            if (!$newAvatar) {
                throw new \RuntimeException('Failed to store image');
            }

            $avatarStorage->delete(StoredFileIdentifier::tryFromRoomAvatar($room));

            $room->update(['room_icon' => $newAvatar->getUuid()]);

            return [
                'url' => $newAvatar->getUrl(),
                'uuid' => $newAvatar->getUuid(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to store image: ' . $e->getMessage());
        }
    }


    public function delete($slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if (!$room->isMember(Auth::id())) {
            throw new AuthorizationException();
        }

        try {
            $room->deleteRoom();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to remove Room Information. Error: $e");
            return false;
        }
    }

}
