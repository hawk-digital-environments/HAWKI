<?php

namespace App\Services\Chat\Room\Traits;

use App\Models\Room;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Services\Chat\Message\MessageHandlerFactory;
use App\Jobs\SendMessage;


use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait RoomMessages{

    public function sendMessage(array $data, string $slug): ?array{

        $room = Room::where('slug', $slug)->firstOrFail();

        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();

        if(!$member){
            throw new AuthorizationException();
        }
        $data['room'] = $room;
        $data['member']= $member;
        $data['message_role'] = 'user';
        $message = $this->messageHandler->create($room, $data);
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

        $message = $this->messageHandler->update($room, $data);

        SendMessage::dispatch($message, true)->onQueue('message_broadcast');

        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

        return $messageData;
    }

    public function markAsRead(array $validatedData, string $slug): bool{
        try{
            $room = Room::where('slug', $slug)->firstOrFail();
            $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
            $message = $room->messages->where('message_id', $validatedData['message_id'])->first();
            $message->addReadSignature($member);
            return true;
        }
        catch(\Exception $e){
            return false;
        }


    }

}
