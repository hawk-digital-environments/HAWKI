<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConv;
use App\Models\Room;
use App\Models\Message;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class GroupMessageHandler extends BaseMessageHandler{


    public function create(AiConv|Room $room, array $data): Message
    {
        $member = $data['member'];
        $nextMessageId = $this->assignID($room, $data['threadId']);
        $message = Message::create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'message_id' => $nextMessageId,
            'message_role' => $data['message_role'],
            'model' => $data['model'] ?? null,
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
        ]);
        $message->addReadSignature($member);

        //ATTACHMENTS
        if(array_key_exists('attachments', $data['content'])){
            $attachments = $data['content']['attachments'];
            if($attachments){
                foreach($attachments as $attach){
                    $this->attachmentService->assignToMessage($message, $attach);
                }
            }
        }

        return $message;
    }


    public function update(AiConv|Room $room, array $data): Message
    {
        $message = $room->getMessageById($data['message_id']);
        if($message->member->user_id != 1 &&
           $message->member->user_id != Auth::id()){
            throw new AuthorizationException();
        }

        $message->update([
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
            'model'=> $data['model'] ?? null,
        ]);

        return $message;
    }


    public function delete(AiConv|Room $room, array $data): bool{
        return false;
    }





}
