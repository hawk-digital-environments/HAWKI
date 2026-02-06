<?php

namespace App\Http\Resources;

use App\Models\Room;
use App\Services\Encryption\EncryptionUtils;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Value\StorageFileCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * @var Room
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatarStorage = app(AvatarStorageService::class);
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->room_name,
            'avatar_path_args' => $avatarStorage->getFileInfo($this->resource->room_icon ?? '', StorageFileCategory::ROOM_AVATAR)?->toRoutePathArgsResource(),
            'slug' => $this->resource->slug,
            'system_prompt' => EncryptionUtils::symmetricCryptoValueFromJson($this->resource->system_prompt),
            'room_description' => EncryptionUtils::symmetricCryptoValueFromJson($this->resource->room_description),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
