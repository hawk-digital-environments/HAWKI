<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConv;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\Chat\Attachment\Db\AttachmentDb;
use App\Services\Storage\Value\StoredFileCategory;
use App\Services\Storage\Value\StoredFileIdentifier;
use Psr\Log\LoggerInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;


class GroupMessageHandler extends AbstractMessageHandler
{


    public function create(
        AiConv|Room $conv,
        array       $data,
        User        $user
    ): Message
    {
        $member = $data['member'];
        $nextMessageId = $this->assignID($conv, $data['threadId']);
        $message = Message::create([
            'room_id' => $conv->id,
            'member_id' => $member->id,
            'message_id' => $nextMessageId,
            'message_role' => $data['message_role'],
            'model' => $data['model'] ?? null,
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
            'metadata' => [
                'tools' => $data['metadata']['tools'] ?? null,
                'params' => $data['metadata']['params'] ?? null,
            ]
        ]);
        $message->addReadSignature($member);

        //ATTACHMENTS
        if (is_array($data['content']['attachments'] ?? null)) {
            foreach ($data['content']['attachments'] as $uuid) {
                $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::GROUP, $uuid);
                $this->storageService->persistTemporaryFile($identifier);
                $storedFile = $this->storageService->retrieve($identifier);
                if ($storedFile) {
                    $this->attachmentService->assignToMessage($message, $storedFile, $user);
                }
            }
        }

        return $message;
    }


    public function update(AiConv|Room $conv, array $data): Message
    {
        $message = $conv->getMessageById($data['message_id']);
        if($message->member->user_id != 1 &&
           $message->member->user_id != Auth::id()){
            throw new AuthorizationException();
        }

        $message->update([
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'content' => $data['content']['text']['ciphertext'],
            'metadata' => [
                'tools' => $data['metadata']['tools'] ?? null,
                'params' => $data['metadata']['params'] ?? null,
            ],
            'model'=> $data['model'] ?? null,
        ]);

        return $message;
    }


    public function delete(AiConv|Room $conv, array $data): bool
    {
        $message = $conv->messages->where('message_id', $data['message_id'])->first();

        foreach ($message->attachments as $attachment) {
            $attachment->delete();
        }

        $message->delete();
        return true;
    }
}
