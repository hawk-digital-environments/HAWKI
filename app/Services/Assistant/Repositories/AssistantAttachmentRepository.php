<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Models\User;
use App\Services\Storage\Values\AttachmentType;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Psr\Log\LoggerInterface;

#[UseModel(AssistantAttachment::class)]
class AssistantAttachmentRepository extends AbstractRepository
{
    public function __construct(
        private readonly LoggerInterface $logger,
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
     * The attachment whose ingestion produced the given RAG managed
     * document id (`adoc_*`) — the local counterpart of a knowledge-base
     * search hit. Ties (a replacement re-ingesting into the same managed
     * document) resolve to the newest attachment.
     */
    public function findOneByRagDocumentId(string $ragDocumentId): ?AssistantAttachment
    {
        return $this->getQuery()
            ->where('rag_document_id', $ragDocumentId)
            ->orderByDesc('id')
            ->first();
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
}
