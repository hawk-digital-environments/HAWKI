<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @var User
     */
    public $resource;

    public function toArray(Request $request): array
    {
        $displayName = $this->resource->name ?: $this->resource->username;
        $username = $this->resource->username;

        if ($this->resource->id === 1) {
            $username = $displayName;
            $displayName = $this->resource->username;
        }
        
        $avatarStorage = app(AvatarStorageService::class);
        return [
            'id' => $this->resource->id,
            'display_name' => $displayName,
            'username' => $username,
            'avatar_url' => $avatarStorage->getFileUrl('profile_avatars', $this->resource->username, $this->resource->avatar_id),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'employee_type' => $this->resource->employeetype,
        ];
    }
}
