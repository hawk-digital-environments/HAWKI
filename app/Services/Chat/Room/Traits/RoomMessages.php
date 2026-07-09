<?php

namespace App\Services\Chat\Room\Traits;

use App\Http\Resources\Legacy\MessageResource;
use App\Jobs\SendMessage;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;


trait RoomMessages{

    public function sendMessage(array $data, string $slug, User $user): ?array
    {

        $room = Room::where('slug', $slug)->firstOrFail();

        $member = $room->members()->where('user_id', $user->id)->firstOrFail();

        $data['room'] = $room;
        $data['member']= $member;
        $data['message_role'] = 'user';

        $message = $this->messageHandler->create($room, $data, $user);

        $broadcastObject = [
            'slug' => $room->slug,
            'message_id'=> $message->message_id,
        ];
        SendMessage::dispatch($broadcastObject, false)->onQueue('message_broadcast');

        return $message->toResource(MessageResource::class)->resolve();
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
        return $message->toResource(MessageResource::class)->resolve();

    }


    public function retrieveMessage(string $message_id, string $slug): array{
        $room = Room::where('slug', $slug)->firstOrFail();
        if(!$room->isMember(Auth::id())){
            throw new AuthorizationException();
        }
        return $room->getMessageById($message_id)->toResource(MessageResource::class)->resolve();
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
