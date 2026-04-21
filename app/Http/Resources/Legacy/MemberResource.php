<?php

namespace App\Http\Resources\Legacy;

use App\Models\Member;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Utils\ServiceLocatorTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Member */
class MemberResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $avatarStorage = $this->getServiceInstance(AvatarStorageService::class);

        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'username' => $this->user->username,
            'role' => $this->role,
            'employeetype' => $this->user->employeetype,
            'avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($this->user))?->getUrl()
        ];
    }
}
