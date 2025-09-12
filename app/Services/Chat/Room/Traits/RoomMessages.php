<?php

namespace App\Services\Chat\Room\Traits;

use App\Jobs\SendMessage;
use App\Models\Room;
use App\Services\Chat\Message\MessageHandlerFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;


trait RoomMessages{

    public function sendMessage(array $data, string $slug): ?array{

        $room = Room::where('slug', $slug)->firstOrFail();

        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();

        if(!$member){
            throw new AuthorizationException();
        }
        $data['room'] = $room;
        $data['member']= $member;

        $messageHandler = MessageHandlerFactory::create('group');
        $message = $messageHandler->create($data, $slug);

        SendMessage::dispatch($message, false)->onQueue('message_broadcast');

        return $message->createMessageObject();
    }


    public function updateMessage(array $data, string $slug): array{

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
        $message = $room->messages->where('message_id', $data['message_id'])->firstOrFail();

        if($message->member->isNot($member)){
            throw new AuthorizationException();
        }

        $message->update([
            'content' => $data['content'],
            'iv' => $data['iv'],
            'tag' => $data['tag']
        ]);

        SendMessage::dispatch($message, true)->onQueue('message_broadcast');

        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

        return $messageData;
    }

    public function markAsRead(array $validatedData, string $slug): void{
        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
        $message = $room->messages->where('message_id', $validatedData['message_id'])->first();
        $message->addReadSignature($member);
    }

}
