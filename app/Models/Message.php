<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;


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
        'metadata',
        'reader_signs'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];


    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return User|null
     */
    public function user(): ?User
    {
        return $this->member?->user;
    }

    /**
     * @param $member
     * @return bool
     */
    public function isReadBy($member): bool
    {
        $hay = json_decode($this->reader_signs, true) ?? [];
        return in_array($member->id, $hay);
    }

    /**
     * @param $member
     * @return void
     */
    public function addReadSignature($member): void
    {
        if (!$this->isReadBy($member)) {
            $signs = json_decode($this->reader_signs, true) ?? [];
            $signs[] = $member->id;
            $this->reader_signs = json_encode($signs);
            $this->save();
        }
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return array|null
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @return array
     */
    public function getTools(): array
    {
        return $this->metadata['tools'];
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->metadata['params'];
    }
}
