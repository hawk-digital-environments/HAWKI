<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Storage\Values\StoredFileIdentifier;
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

        $avatarIdentifier = StoredFileIdentifier::tryFromUserAvatar($this->resource);

        return [
            'id' => $this->resource->id,
            'display_name' => $displayName,
            'username' => $username,
            'bio' => $this->resource->bio,
            'avatar' => $avatarIdentifier ? (string)$avatarIdentifier : null,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'employee_type' => $this->resource->employeetype,
        ];
    }
}
