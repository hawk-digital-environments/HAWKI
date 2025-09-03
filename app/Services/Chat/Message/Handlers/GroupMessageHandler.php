<?php


namespace App\Services\Chat\Message\Handlers;

use App\Events\MessageSentEvent;
use App\Events\MessageUpdateEvent;
use App\Models\Message;
use App\Models\Room;
use App\Services\Chat\Attachment\AttachmentService;
use App\Services\Message\LegacyMessageHelper;
use Illuminate\Support\Facades\Auth;


class GroupMessageHandler extends BaseMessageHandler{
    public function __construct(
        AttachmentService           $attachmentService,
        private LegacyMessageHelper $messageHelper
    )
    {
        parent::__construct($attachmentService);
    }
    
    public function create(array $data, string $slug): ?Message {

        $room = $data['room'];
        $member = $data['member'];
        
        $threadInfo = $this->messageHelper->getThreadInfo(
            $data['threadID'],
            ($data['thread_id_version'] ?? 1) === 1
        );
        
        $messageRole = 'user';
        $nextMessageId = $this->assignID($room, $threadInfo->legacyThreadId);
        
        $message = Message::create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'user_id' => Auth::id(),
            'message_id' => $nextMessageId,
            'message_role' => $messageRole,
            'thread_id' => $threadInfo->threadId,
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

    public function update(array $data, string $slug): ?Message{

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();

        if(!$room || !$member){
            return null;
        }
        
        $message = $room->messages->where('message_id', $data['message_id'])->firstOrFail();

        $message->update([
            'content' => $data['content'],
            'iv' => $data['iv'],
            'tag' => $data['tag']
        ]);
        
        MessageUpdateEvent::dispatch($message);

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
