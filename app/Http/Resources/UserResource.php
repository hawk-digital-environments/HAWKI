<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\File\PublicStoragePaths;
use Illuminate\Container\Container;
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
        $storagePaths = Container::getInstance()->get(PublicStoragePaths::class);

        $displayName = $this->resource->name ?: $this->resource->username;
        $username = $this->resource->username;

        if ($this->resource->id === 1) {
            $username = $displayName;
            $displayName = $this->resource->username;
        }

        return [
            'id' => $this->resource->id,
            'display_name' => $displayName,
            'username' => $username,
            'avatar_url' => $storagePaths->getUserProfileAvatarPath($this->resource),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'employee_type' => $this->resource->employeetype,
        ];
    }
}
