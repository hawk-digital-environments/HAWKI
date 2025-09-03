<?php

namespace App\Http\Resources;

use App\Models\Message;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonException;

class MessageResource extends JsonResource
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
            'thread_id' => $this->resource->thread_id,
            'read_by' => $this->resource->reader_signs === null ? [] : json_decode($this->resource->reader_signs, true, 512, JSON_THROW_ON_ERROR),
            'model' => $this->resource->model,
            'content' => (string)new SymmetricCryptoValue(
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
