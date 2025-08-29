<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\User;
use App\Models\AiConv;
use App\Models\AiConvMsg;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;


class PrivateMessageHandler extends BaseMessageHandler{



    public function create(array $data, string $slug): array {

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return [
                        'success'=> false,
                        'error' => 'Access denied'
            ];
        }

        $user = $data['isAi'] ? User::find(1) : Auth::user();
        $messageRole = $data['isAi'] ? 'assistant' : 'user';

        $nextMessageId = $this->assignID($conv, $data['threadID']);
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

        $messageData = $message->createMessageObject();
        return [
            'success'=> true,
            'messageData'=> $messageData
        ];
    }



    public function update(array $data, string $slug){

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json(['error' => 'Access denied'], 403);
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

        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

        return $messageData;

    }


    public function delete(array $data, string $slug){

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json([
                'success'=> false,
                'error' => 'Access denied'
            ], 403);
        }

        $message = $conv->messages->where('message_id', $data['message_id'])->first();
        $message->delete();

    }
}
