<?php

namespace App\Services\Chat\Attachment\Repositories;


use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\Attachment\Values\AttachmentType;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Exception;
use Psr\Log\LoggerInterface;

class AttachmentRepository extends AbstractRepository
{
    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
    }

    public function findOneByStoredFileIdentifier(StoredFileIdentifier $identifier): Attachment|null
    {
        return $this->findOneByIdAndCategory($identifier->uuid, $identifier->category);
    }

    public function findOneByIdAndCategory(string $uuid, StoredFileCategory $category): Attachment|null
    {
        return $this->getQuery()->where('uuid', $uuid)->where('category', $category->value)->first();
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
