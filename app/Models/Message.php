<?php

namespace App\Models;

use Hamcrest\Core\IsTypeOf;
use Illuminate\Database\Eloquent\Model;
use App\Services\Storage\FileStorageService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Services\Storage\AvatarStorageService;

use Illuminate\Support\Facades\Log;



class Message extends Model
{
    // NOTE: CONTENT = RAW CONTENT

    protected $fillable = [
        'room_id',
        'message_id',
        'message_role',
        'member_id',
        'model',
        'iv',
        'tag',
        'content',
        'reader_signs'
    ];


    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
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

        return [
            'member_id' => $member->id,
            'member_name' => $member->user->name,
            'message_role' => $this->message_role,
            'message_id' => $this->message_id,
            'read_status'=> $readStat,

            'author' => [
                'username' => $member->user->username,
                'name' => $member->user->name,
                'isRemoved' => $member->isRemoved,
                'avatar_url' =>!empty($user->avatar_id)
                                ? $avatarStorage->getUrl($member->user->avatar_id, 'profile_avatars')
                                : null,
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
    }




    public function isReadBy($member): bool
    {
        $hay = json_decode($this->reader_signs, true) ?? [];
        return in_array($member->id, $hay);
    }

    public function addReadSignature($member): void
    {
        if (!$this->isReadBy($member)) {
            $signs = json_decode($this->reader_signs, true) ?? [];
            $signs[] = $member->id;
            $this->reader_signs = json_encode($signs);
            $this->save();
        }
    }



    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }


    public function attachmentsAsArray(): ?array
    {
        if ($this->attachments->isEmpty()) {
            return null;
        }
        $storageService = app(FileStorageService::class);

        return $this->attachments->map(function ($attach) use ($storageService) {
            return [
                'fileData' => [
                    'uuid'     => $attach->uuid,
                    'name'     => $attach->name,
                    'category' => $attach->category,
                    'type'     => $attach->type,
                    'mime'     => $attach->mime,
//                    'url'      => $storageService->getUrl(
//                        uuid: $attach->uuid,
//                        category: $attach->category
//                    ),
                ],
            ];
        })->toArray();
    }

}
