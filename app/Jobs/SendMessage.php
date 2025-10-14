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


    private array $data;
    private bool $isUpdate;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data, bool $isUpdate = false)
    {
        $this->data = $data;
        $this->isUpdate = $isUpdate;

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $type = $this->isUpdate ? "messageUpdate" : "message";
        $boradcastPacket = [
            'type' => $type,
            'data' => [
                'slug' => $this->data['slug'],
                'message_id' => $this->data['message_id']
            ]
        ];

        broadcast(new RoomMessageEvent($boradcastPacket));
    }
}
