<?php

namespace App\Services\Chat\Room\Traits;

use App\Models\Room;
use App\Models\Message;

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

        $broadcastObject = [
            'slug' => $room->slug,
            'message_id'=> $message->message_id,
        ];
        SendMessage::dispatch($broadcastObject, false)->onQueue('message_broadcast');

        return $message->createMessageObject();
    }


    public function updateMessage(array $data, string $slug): array{

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
        $message = $room->getMessageById($data['message_id']);

        if($message->member->isNot($member)){
            throw new AuthorizationException();
        }

        $message = $this->messageHandler->update($room, $data);
        $broadcastObject = [
            'slug' => $room->slug,
            'message_id'=> $message->message_id,
        ];
        SendMessage::dispatch($broadcastObject, true)->onQueue('message_broadcast');
        return $message->createMessageObject();

    }


    public function retrieveMessage(string $message_id, string $slug): array{
        $room = Room::where('slug', $slug)->firstOrFail();
        if(!$room->isMember(Auth::id())){
            throw new AuthorizationException();
        }
        $message = $room->getMessageById($message_id);
        return $message->createMessageObject();
    }

    public function markAsRead(array $validatedData, string $slug): bool{
        try{
            $room = Room::where('slug', $slug)->firstOrFail();
            $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
            $message = $room->getMessageById($validatedData['message_id']);
            $message->addReadSignature($member);
            return true;
        }
        catch(\Exception $e){
            return false;
        }


    }

}
