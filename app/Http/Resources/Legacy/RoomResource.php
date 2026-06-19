<?php

namespace App\Http\Resources\Legacy;

use App\Models\Room;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    use ServiceLocatorTrait;

    private string|null $role = null;

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $avatarStorage = $this->getService(AvatarStorageService::class);

        return [
            'id' => $this->id,
            'name' => $this->room_name,
            'room_icon' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromRoomAvatar($this->resource))?->getUrl() ?? '',
            'slug' => $this->slug,
            'system_prompt' => $this->system_prompt,
            'room_description' => $this->room_description,
            'role' => $this->role,
            'members' => $this->members->toResourceCollection(MemberResource::class),
            'messagesData' => $this->messages->toResourceCollection(MessageResource::class)
        ];
    }
}
