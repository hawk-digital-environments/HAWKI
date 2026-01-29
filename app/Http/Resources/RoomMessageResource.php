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
            'read_by' => $this->getReadByAsUserIds(),
            'model' => $this->resource->model,
            'content' => EncryptionUtils::symmetricCryptoValueFromStrings(
                $this->resource->iv,
                $this->resource->tag,
                $this->resource->content
            ),
            'attachments' => AttachmentResource::collection($this->resource->attachments()->get()),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
    
    /**
     * In the frontend it is much more efficient to have an array of user IDs who have read the message
     * instead of the member ids, because like this we can efficiently check if the current user has read the message,
     * and we can also easily show the avatars of the users who have read the message.
     * @return array
     * @throws JsonException
     */
    private function getReadByAsUserIds(): array
    {
        $readByMembers = json_decode($this->resource->reader_signs, true, 512, JSON_THROW_ON_ERROR);
        if ($readByMembers === null) {
            return [];
        }
        
        $roomMembers = $this->resource->room->members->keyBy('id');
        
        $userIds = [];
        
        foreach ($readByMembers as $memberId) {
            $member = $roomMembers->get($memberId);
            if ($member !== null) {
                $userIds[] = $member->user_id;
            }
        }
        
        return $userIds;
    }
}
