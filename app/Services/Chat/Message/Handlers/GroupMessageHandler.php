<?php


namespace App\Services\Chat\Message\Handlers;

use App\Events\MessageSentEvent;
use App\Events\MessageUpdateEvent;
use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\User;
use App\Models\Member;
use App\Models\Message;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\Message\ThreadIdHelper;
use App\Services\Chat\Attachment\AttachmentService;

use Illuminate\Support\Facades\Auth;


class GroupMessageHandler extends BaseMessageHandler{
    public function __construct(
        AttachmentService           $attachmentService,
        private ThreadIdHelper $messageHelper
    )
    {
        parent::__construct($attachmentService);
    }

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
            'thread_id' => $this->messageHelper->getThreadIdForRoomAndThreadIndex($room, $data['threadID']),
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
        
        MessageSentEvent::dispatch($message);

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
        
        MessageUpdateEvent::dispatch($message);

        return $message;
    }


    public function delete(AiConv|Room $room, array $data): bool{
        return false;
    }





}
