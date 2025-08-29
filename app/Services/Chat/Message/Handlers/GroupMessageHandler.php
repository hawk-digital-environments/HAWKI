<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\User;
use App\Models\Member;
use App\Models\Message;

use App\Services\Chat\Attachment\AttachmentService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class GroupMessageHandler extends BaseMessageHandler{


    public function create(array $data, string $slug): ?Message {

        $room = $data['room'];
        $member = $data['member'];

        $messageRole = 'user';
        $nextMessageId = $this->assignID($room, $data['threadID']);
        $message = Message::create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'user_id' => Auth::id(),
            'message_id' => $nextMessageId,
            'message_role' => $messageRole,
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


    public function update(array $data, string $slug): ?Message{

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();

        if(!$room || !$member){
            return null;
        }

        $message = $room->messages->where('message_id', $data['message_id'])->first();

        $message->update([
            'content' => $data['content'],
            'iv' => $data['iv'],
            'tag' => $data['tag']
        ]);

        return $message;
    }


    public function markAsRead(string $message_id, string $slug): bool{

        try{
            $room = Room::where('slug', $slug)->firstOrFail();
            $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
            $message = $room->messages->where('message_id', $message_id)->first();
            $message->addReadSignature($member);
            return true;
        }
        catch(\Exception $e){
            return false;
        }

    }



    public function delete(array $data, string $slug){




    }





}
