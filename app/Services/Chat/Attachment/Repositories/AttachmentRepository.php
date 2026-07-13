<?php

declare(strict_types=1);

namespace App\Services\Chat\Attachment\Repositories;

use App\Models\AiConvMsg;
use App\Models\Assistants\Assistant;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\Attachment\Values\AttachmentType;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Psr\Log\LoggerInterface;

class AttachmentRepository extends AbstractRepository
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function findOneByStoredFileIdentifier(StoredFileIdentifier $identifier): ?Attachment
    {
        return $this->findOneByIdAndCategory($identifier->uuid, $identifier->category);
    }

    public function findOneByIdAndCategory(string $uuid, StoredFileCategory $category): ?Attachment
    {
        return $this->getQuery()->where('uuid', $uuid)->where('category', $category->value)->first();
    }

    public function delete(Attachment $attachment): void
    {
        $attachment->delete();
    }

    public function deleteForMessage(AiConvMsg|Message $message): void
    {
        foreach ($message->attachments as $attachment) {
            $this->delete($attachment);
        }
    }

    public function assignToMessage(
        AiConvMsg|Message $message,
        StoredFile $file,
        User $user,
    ): bool {
        return $this->createAttachment($message, $file, $user);
    }

    public function assignToAssistant(
        Assistant $assistant,
        StoredFile $file,
        User $user,
    ): bool {
        return $this->createAttachment($assistant, $file, $user);
    }

    /**
     * Persists an Attachment row linking the given attachable (message or
     * assistant) to the stored file, owned by the given user.
     */
    private function createAttachment(
        AiConvMsg|Assistant|Message $attachable,
        StoredFile $file,
        User $user,
    ): bool {
        try {
            $attachable->attachments()->create([
                'uuid' => $file->getUuid(),
                'name' => $file->getOriginalFilename(),
                'category' => $file->getCategory()->value,
                'mime' => $file->getMimeType(),
                'type' => AttachmentType::fromFileType($file->getFileType())->value,
                'user_id' => $user->id,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to assign attachment to attachable',
                ['exception' => $e,
                    'attachable_id' => $attachable->id,
                    'attachment_data' => [
                        'UUID' => $file->getUuid(),
                        'category' => $file->getCategory()->value,
                    ],
                ],
            );

            return false;
        }
    }
}
