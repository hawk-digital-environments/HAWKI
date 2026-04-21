<?php

namespace App\Http\Resources;

use App\Models\Room;
use App\Services\Encryption\EncryptionUtils;
use App\Services\Storage\Values\StoredFileIdentifier;
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
        $avatarIdentifier = StoredFileIdentifier::tryFromRoomAvatar($this->resource);

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->room_name,
            'avatar' => $avatarIdentifier ? (string)$avatarIdentifier : null,
            'slug' => $this->resource->slug,
            'system_prompt' => EncryptionUtils::symmetricCryptoValueFromJson($this->resource->system_prompt),
            'room_description' => EncryptionUtils::symmetricCryptoValueFromJson($this->resource->room_description),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
