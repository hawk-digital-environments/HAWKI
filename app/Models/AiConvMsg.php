<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\AvatarStorageService;

class AiConvMsg extends Model
{
    use HasFactory;

    protected $fillable = [
        'conv_id',
        'user_id',
        'message_role',
        'message_id',
        'model',
        'iv',
        'tag',
        'content',
        'completion',
    ];

    // Define the relationship with AiConv
    public function conversation()
    {
        return $this->belongsTo(AiConv::class, 'conv_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function createMessageObject(): array
    {
        $avatarStorage = app(AvatarStorageService::class);

        //if AI is the author, then username and name are the same.
        //if User has created the message then fetch the name from model.
        $user =  $this->user;
        $msgData = [
            'message_role' => $this->message_role,
            'message_id' => $this->message_id,
            'author' => [
                'username' => $user->username,
                'name' => $user->name,
                'avatar_url' => $avatarStorage->getFileUrl('profile_avatars', $user->username, $user->avatar_id),
            ],
            'model' => $this->model,

            'content' => [
                'text' => [
                    'ciphertext'=> $this->content,
                    'iv' => $this->iv,
                    'tag' => $this->tag,
                ],
                'attachments' => $this->attachmentsAsArray(),
            ],
            'completion' => $this->completion,
            'created_at' => $this->created_at->format('Y-m-d+H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d+H:i'),
        ];

        return $msgData;
    }


    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }


    public function attachmentsAsArray()
    {
        $attachments = $this->attachments;

        if ($attachments->isEmpty()) {
            return null;
        }
        $storageService = app(FileStorageService::class);

        return $attachments->map(function ($attach) use ($storageService) {
            return [
                'fileData' => [
                    'uuid'     => $attach->uuid,
                    'name'     => $attach->name,
                    'category' => $attach->category,
                    'type'     => $attach->type,
                    'mime'     => $attach->mime,
                    'url'      => $storageService->getFileUrl(
                        uuid: $attach->uuid,
                        category: $attach->category
                    ),
                ],
            ];
        })->toArray();
    }

}
