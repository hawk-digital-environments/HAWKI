<?php

namespace App\Http\Resources;

use App\Models\Room;
use App\Services\File\PublicStoragePaths;
use Illuminate\Container\Container;
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
        $storagePaths = Container::getInstance()->get(PublicStoragePaths::class);
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->room_name,
            'room_icon' => $storagePaths->getRoomAvatarPath($this->resource),
            'slug' => $this->resource->slug,
            'system_prompt' => $this->resource->system_prompt,
            'room_description' => $this->resource->room_description,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
