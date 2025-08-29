<?php

namespace App\Models;

use App\Events\MessageUpdateEvent;
use Illuminate\Database\Eloquent\Model;
use App\Services\Storage\FileStorageService;
use Illuminate\Support\Facades\Auth;
use App\Services\Storage\AvatarStorageService;



class Message extends Model
{
    // NOTE: CONTENT = RAWCONTENT

    protected $fillable = [
        'room_id',
        'thread_id',
        'message_id',
        'message_role',
        'member_id',
        'model',
        'iv',
        'tag',
        'content',
        'reader_signs'
    ];


    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->member->user();
    }


    public function createMessageObject(): array
    {
        $avatarStorage = app(AvatarStorageService::class);

        $member = $this->member;
        $room = $this->room;

        $requestMember = $room->members()->where('user_id', Auth::id())->firstOrFail();

        $readStat = $this->isReadBy($requestMember);

        $msgData = [
            'member_id' => $member->id,
            'member_name' => $member->user->name,
            'message_role' => $this->message_role,
            'message_id' => $this->message_id,
            'read_status'=> $readStat,

            'author' => [
                'username' => $member->user->username,
                'name' => $member->user->name,
                'isRemoved' => $member->isRemoved,
                'avatar_url' =>$avatarStorage->getFileUrl('profile_avatars', $member->user->username, $member->user->avatar_id),
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
            'created_at' => $this->created_at->format('Y-m-d+H:i'),
            'updated_at' => $this->updated_at->format('Y-m-d+H:i'),
        ];

        return $msgData;
    }




    public function isReadBy($member)
    {
        $hay = json_decode($this->reader_signs, true) ?? [];
        return in_array($member->id, $hay);
    }

    public function addReadSignature($member)
    {
        if (!$this->isReadBy($member)) {
            $signs = json_decode($this->reader_signs, true) ?? [];
            $signs[] = $member->id;
            $this->reader_signs = json_encode($signs);
            MessageUpdateEvent::dispatch($this);
            $this->save();
        }
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
