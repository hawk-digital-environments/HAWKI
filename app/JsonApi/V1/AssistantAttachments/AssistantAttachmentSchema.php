<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantAttachments;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Storage\Values\StoredFileIdentifier;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Knowledge files owned by an assistant. Serialised only as an include of
 * the assistants resource (no top-level routes); the storage identifier is
 * derived from the fixed ASSISTANT storage category. `rag_status` lets the
 * frontend poll ingestion progress (null = RAG not applicable).
 */
class AssistantAttachmentSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = AssistantAttachment::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('uuid'),
            Str::make('name'),
            Str::make('type'),
            Str::make('mime'),
            Number::make('size')->readOnly(),
            // Set only via the dedicated actions/attachment/review admin
            // action (AssistantController::reviewAttachment) — never a plain
            // attribute PATCH, since it's strictly an admin judgment.
            Str::make('review_status')->readOnly(),
            DateTime::make('created_at')->readOnly(),
            Str::make('rag_status'),
            Str::make('rag_error'),
            Str::make('identifier')->extractUsing(function (AssistantAttachment $assistantAttachment) {
                return (string)StoredFileIdentifier::fromAssistantAttachment($assistantAttachment);
            }),
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
        ];
    }
}
