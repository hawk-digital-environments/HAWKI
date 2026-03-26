<?php

namespace App\Http\Resources\Legacy;

use App\Models\Message;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Value\StoredFileIdentifier;
use App\Utils\ServiceLocatorTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $avatarStorage = $this->getServiceInstance(AvatarStorageService::class);
        $requestMember = $this->room->members()->where('user_id', $request->user()->id)->firstOrFail();

        return [
            'member_id' => $this->member->id,
            'member_name' => $this->member->user->name,
            'message_role' => $this->message_role,
            'message_id' => $this->message_id,
            'read_status' => $this->isReadBy($requestMember),

            'author' => [
                'username' => $this->member->user->username,
                'name' => $this->member->user->name,
                'isRemoved' => $this->member->isRemoved,
                'avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($this->member->user))?->getUrl()
            ],
            'model' => $this->model,

            'content' => [
                'text' => [
                    'ciphertext' => $this->content,
                    'iv' => $this->iv,
                    'tag' => $this->tag,
                ],
                'attachments' => $this->attachments->toResourceCollection(AttachmentResource::class),
            ],

            'metadata' => $this->getMetadata(),
            'created_at' => $this->created_at->format('Y-m-d+H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d+H:i'),
        ];
    }
}
