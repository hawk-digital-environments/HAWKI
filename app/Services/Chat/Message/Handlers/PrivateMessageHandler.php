<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Room;
use App\Models\User;
use App\Services\Chat\Attachment\Db\AttachmentDb;
use App\Services\Storage\Value\StoredFileCategory;
use App\Services\Storage\Value\StoredFileIdentifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class PrivateMessageHandler extends AbstractMessageHandler
{
    public function create(
        AiConv|Room $conv,
        array       $data,
        User        $user,
    ): AiConvMsg
    {
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }
//        \Log::info($data);
        $user = $data['isAi'] ? User::findOrFail(1) : $user;
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
            'metadata' => [
                'tools' => $data['metadata']['tools'] ?? null,
                'params' => $data['metadata']['params'] ?? null,
            ],
        ]);

        //ATTACHMENTS
        if (is_array($data['content']['attachments'] ?? null)) {
            foreach ($data['content']['attachments'] as $uuid) {
                $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
                $this->storageService->persistTemporaryFile($identifier);
                $storedFile = $this->storageService->retrieve($identifier);
                if ($storedFile) {
                    $this->attachmentService->assignToMessage($message, $storedFile, $user);
                }
            }
        }
        return $message;
    }


    public function update(AiConv|Room $conv, array $data): AiConvMsg
    {
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
            'completion' => $data['completion'],
            'metadata' => [
                'tools' => $data['metadata']['tools'] ?? null,
                'params' => $data['metadata']['params'] ?? null,
            ]
        ]);

        return $message;
    }


    public function delete(AiConv|Room $conv, array $data): bool
    {
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }

        $message = $conv->messages->where('message_id', $data['message_id'])->first();

        foreach ($message->attachments as $attachment) {
            $attachment->delete();
        }

        $message->delete();
        return true;
    }
}
