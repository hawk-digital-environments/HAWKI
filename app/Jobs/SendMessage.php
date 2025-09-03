<?php

namespace App\Jobs;

use App\Events\RoomMessageEvent;
use App\Models\Message;
use App\Models\Member;
use App\Models\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class SendMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    private Message $message;
    private bool $isUpdate;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message, bool $isUpdate = false)
    {
        $this->message = $message;
        $this->isUpdate = $isUpdate;

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $member = Member::findOrFail($this->message->member_id);

        $type = $this->isUpdate ? "messageUpdate" : "message";
        $messageData = [
            'room_id' => $this->message->room_id,
            'member_id' => $this->message->member_id,
            'author' => [
                'username' => $member->user->username,
                'name' => $member->user->name,
                'isRemoved' => $member->isRemoved,
                'avatar_url' => $member->user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $member->user->avatar_id) : null,
            ],
            'model' => $this->message->model,
            'message_role' => $this->message->message_role,
            'message_id' => $this->message->message_id,
            'iv' => $this->message->iv,
            'tag' => $this->message->tag,
            'content' => [
                'text'=>[
                    'ciphertext' => $this->message->content,
                    'iv' => $this->message->iv,
                    'tag' => $this->message->tag,
                ],
            ],
            'read_status'=> false,

            'created_at' => $this->message->created_at->format('Y-m-d+H:i'),
            'updated_at' => $this->message->updated_at->format('Y-m-d+H:i'),
        ];
        $boradcastObject = [
            'type' => $type,
            'messageData' => $messageData
        ];

        broadcast(new RoomMessageEvent($boradcastObject));
    }
}
