<?php

namespace App\Models;

use App\Services\Chat\Events\MessageUpdatedEvent;
use App\Policies\RoomMessagePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[UsePolicy(RoomMessagePolicy::class)]
class Message extends Model
{
    // NOTE: CONTENT = RAW CONTENT

    protected $fillable = [
        'room_id',
        'thread_id',
        'has_thread',
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
     * @return HasOneThrough<User, Member, $this>
     */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Member::class, 'id', 'id', 'member_id', 'user_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
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
            MessageUpdatedEvent::dispatch($this);
            $this->save();
        }
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
