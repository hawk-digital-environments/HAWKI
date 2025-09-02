<?php

namespace App\Http\Resources;

use App\Models\Room;
use App\Services\Storage\AvatarStorageService;
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
            'room_icon' => $avatarStorage->getFileUrl('room_avatars', $this->resource->slug, $this->resource->room_icon),
            'slug' => $this->resource->slug,
            'system_prompt' => $this->resource->system_prompt,
            'room_description' => $this->resource->room_description,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
