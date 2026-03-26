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

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getTools()
    {
        return $this->metadata['tools'];
    }

    public function getParameters()
    {
        return $this->metadata['params'];
    }
}
