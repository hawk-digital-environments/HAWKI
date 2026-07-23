<?php


namespace App\Services\Chat\Message\Handlers;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
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
                'citations' => $data['metadata']['citations'] ?? null,
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


    public function update(AiConv|Room $conv, array $data): AiConvMsg|null
    {
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }

        //find the target message
        /** @var AiConvMsg|null $message */
        $message = $conv->messages->where('message_id', $data['message_id'])->first();

        $message?->update([
            'content' => $data['content']['text']['ciphertext'],
            'iv' => $data['content']['text']['iv'],
            'tag' => $data['content']['text']['tag'],
            'model' => $data['model'],
            'completion' => $data['completion'],
            'metadata' => [
                'tools' => $data['metadata']['tools'] ?? null,
                'params' => $data['metadata']['params'] ?? null,
                'citations' => $data['metadata']['citations'] ?? null,
            ]
        ]);

        // Add newly added attachments
        if (is_array($data['content']['attachments'] ?? null)) {
            foreach ($data['content']['attachments'] as $uuid) {
                $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
                if ($this->attachmentService->findOneByStoredFileIdentifier($identifier)) {
                    continue; //skip already attached files
                }
                $this->storageService->persistTemporaryFile($identifier);
                $storedFile = $this->storageService->retrieve($identifier);
                if ($storedFile) {
                    $this->attachmentService->assignToMessage($message, $storedFile, Auth::user());
                }
            }
        }

        // Clean up removed attachments
        foreach ($message?->attachments()->pluck('uuid')->toArray() ?? [] as $existingUuid) {
            if (!in_array($existingUuid, $data['content']['attachments'] ?? [], true)) {
                //attachment removed, delete the record and the file
                $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $existingUuid);
                $attachment = $this->attachmentService->findOneByStoredFileIdentifier($identifier);
                if ($attachment) {
                    $attachment->delete();
                    $this->storageService->delete($identifier);
                }
            }
        }
        return $message;
    }


    public function delete(AiConv|Room $conv, array $data): bool
    {
        if ($conv->user_id !== Auth::id()) {
            throw new AuthorizationException();
        }

        /** @var Message|null $message */
        $message = $conv->messages->where('message_id', $data['message_id'])->first();
        if (!$message) {
            return false;
        }

        foreach ($message->attachments as $attachment) {
            $attachment->delete();
        }

        $message->delete();
        return true;
    }
}
