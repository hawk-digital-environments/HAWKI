<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\User;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class PrivateMessageHandler extends BaseMessageHandler{



    public function create(AiConv|Room $conv, array $data): AiConvMsg {
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }

        $user = $data['isAi'] ? User::find(1) : Auth::user();
        $messageRole = $data['isAi'] ? 'assistant' : 'user';

        $nextMessageId = $this->assignID($conv, $data['threadId']);
        $message = AiConvMsg::create([
            'conv_id' => $conv->id,
            'user_id' => $user->id,
            'model' => $data['isAi'] ? $data['model'] : null,

            'message_role' => $messageRole,
            'message_id' => $nextMessageId,
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
            'completion' => $data['completion'],
        ]);

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



    public function update(AiConv|Room $conv, array $data): AiConvMsg{
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }

        //find the target message
        $message = $conv->messages->where('message_id', $data['message_id'])->first();

        $message->update([
            'content' => $data['content']['text']['ciphertext'],
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'model' => $data['model'],
            'completion' => $data['completion']
        ]);

        return $message;
    }


    public function delete(AiConv|Room $conv, array $data): bool{
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }

        $message = $conv->messages->where('message_id', $data['message_id'])->first();
        if (!$message->user->is(Auth::user())) {
            throw new AuthorizationException();
        }

        $attachmentService = app(AttachmentService::class);
        $attachments = $message->attachments;
        foreach ($attachments as $attachment) {
            $attachmentService->delete($attachment);
        }

        $message->delete();
        return true;
    }
}
