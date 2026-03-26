<?php

namespace App\Services\Chat\Attachment\Db;


use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\Attachment\Value\AttachmentType;
use App\Services\Storage\Value\StoredFile;
use App\Services\Storage\Value\StoredFileCategory;
use App\Services\Storage\Value\StoredFileIdentifier;
use Exception;
use Psr\Log\LoggerInterface;

readonly class AttachmentDb
{
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    public function findByStoredFileIdentifier(StoredFileIdentifier $identifier): Attachment|null
    {
        return $this->findByIdAndCategory($identifier->uuid, $identifier->category);
    }

    public function findByIdAndCategory(string $uuid, StoredFileCategory $category): Attachment|null
    {
        return Attachment::where('uuid', $uuid)->where('category', $category->value)->first();
    }

    public function assignToMessage(
        AiConvMsg|Message $message,
        StoredFile        $file,
        User              $user
    ): bool
    {
        try {
            $message->attachments()->create([
                'uuid' => $file->getUuid(),
                'name' => $file->getOriginalFilename(),
                'category' => $file->getCategory()->value,
                'mime' => $file->getMimeType(),
                'type' => AttachmentType::fromFileType($file->getFileType())->value,
                'user_id' => $user->id
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to assign attachment to message",
                ['exception' => $e,
                    "message_id" => $message->id,
                    "attachment_data" => [
                        "UUID" => $file->getUuid(),
                        "category" => $file->getCategory()->value
                    ]
                ]
            );
            return false;
        }
    }
}
