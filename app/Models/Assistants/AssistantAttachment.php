<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Models\User;
use App\Services\Assistant\Events\AssistantAttachmentDeletingEvent;
use App\Services\Assistant\Values\AssistantAttachmentReviewStatus;
use App\Services\Rag\Values\RagIngestionStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property null|\Illuminate\Support\Carbon        $created_at
 * @property int                                    $id
 * @property string                                 $mime
 * @property string                                 $name
 * @property null|string                            $rag_batch_id
 * @property null|string                            $rag_document_id
 * @property null|RagIngestionStatus                $rag_status
 * @property null|AssistantAttachmentReviewStatus   $review_status
 * @property null|int                               $size
 * @property string                                 $type
 * @property null|\Illuminate\Support\Carbon        $updated_at
 * @property string                                 $uuid
 */
#[Table('assistant_attachments')]
class AssistantAttachment extends Model
{
    protected $dispatchesEvents = [
        'deleting' => AssistantAttachmentDeletingEvent::class,
    ];

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'mime',
        'size',
        'user_id',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'rag_status' => RagIngestionStatus::class,
            'rag_ingested_at' => 'datetime',
            'review_status' => AssistantAttachmentReviewStatus::class,
        ];
    }
}
