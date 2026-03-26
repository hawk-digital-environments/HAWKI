<?php

namespace App\Http\Resources\Legacy;

use App\Models\AiConvMsg;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Value\StoredFileIdentifier;
use App\Utils\ServiceLocatorTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiConvMsg */
class AiConvMsgResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $avatarStorage = $this->getServiceInstance(AvatarStorageService::class);

        return [
            'message_role' => $this->message_role,
            'message_id' => $this->message_id,
            'author' => [
                'username' => $this->user->username,
                'name' => $this->user->name,
                'avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($this->user))?->getUrl()
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
            'metadata' => $this->metadata,
            'completion' => $this->completion,
            'created_at' => $this->created_at->format('Y-m-d+H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d+H:i'),
        ];
    }
}
