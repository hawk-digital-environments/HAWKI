<?php

namespace App\Http\Resources;

use App\Models\Message;
use App\Services\Encryption\EncryptionUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonException;

class RoomMessageResource extends JsonResource
{
    /**
     * @var Message
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     * @throws JsonException
     */
    public function toArray(?Request $request = null): array
    {
        return [
            'id' => $this->resource->id,
            'room_id' => $this->resource->room_id,
            'member_id' => $this->resource->member->id,
            'has_thread' => $this->resource->has_thread,
            'thread_id' => $this->resource->thread_id,
            'read_by' => $this->resource->reader_signs === null ? [] : json_decode($this->resource->reader_signs, true, 512, JSON_THROW_ON_ERROR),
            'model' => $this->resource->model,
            'content' => EncryptionUtils::symmetricCryptoValueFromStrings(
                $this->resource->iv,
                $this->resource->tag,
                $this->resource->content
            ),
            'attachments' => AttachmentResource::collection($this->resource->attachments),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
