<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Models\User;
use App\Services\Rag\Values\RagIngestionStatus;
use App\Services\Storage\Values\AttachmentType;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;

#[UseModel(AssistantAttachment::class)]
class AssistantAttachmentRepository extends AbstractRepository
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    public function findOneByStoredFileIdentifier(StoredFileIdentifier $identifier): ?AssistantAttachment
    {
        return $this->findOneByUuid($identifier->uuid);
    }

    public function findOneByUuid(string $uuid): ?AssistantAttachment
    {
        return $this->getQuery()->where('uuid', $uuid)->first();
    }

    /**
     * Persists an AssistantAttachment row linking the stored file to the
     * assistant, owned by the given user. Returns the created model, or
     * null when persisting failed.
     */
    public function assignToAssistant(
        Assistant $assistant,
        StoredFile $file,
        User $user,
    ): ?AssistantAttachment {
        try {
            return $assistant->assistantAttachments()->create([
                'uuid' => $file->getUuid(),
                'name' => $file->getOriginalFilename(),
                'mime' => $file->getMimeType(),
                'type' => AttachmentType::fromFileType($file->getFileType())->value,
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to assign attachment to assistant',
                ['exception' => $e,
                    'assistant_id' => $assistant->id,
                    'attachment_data' => [
                        'UUID' => $file->getUuid(),
                        'category' => $file->getCategory()->value,
                    ],
                ],
            );

            return null;
        }
    }

    /**
     * Advances the RAG ingestion state of an attachment. Sets the ingest
     * timestamp automatically when the status becomes INGESTED; passing
     * null for taskId/error leaves the stored values untouched.
     */
    public function updateRagState(
        int $assistantAttachmentId,
        RagIngestionStatus $status,
        ?string $taskId = null,
        ?string $error = null,
    ): void {
        $update = ['rag_status' => $status->value];

        if (null !== $taskId) {
            $update['rag_task_id'] = $taskId;
        }

        if (null !== $error) {
            $update['rag_error'] = $error;
        }

        if (RagIngestionStatus::INGESTED === $status) {
            $update['rag_ingested_at'] = $this->clock->now();
        }

        $this->getQuery()->whereKey($assistantAttachmentId)->update($update);
    }
}
