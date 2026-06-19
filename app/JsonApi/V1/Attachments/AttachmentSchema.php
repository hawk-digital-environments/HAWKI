<?php

namespace App\JsonApi\V1\Attachments;

use App\Models\Attachment;
use App\Services\Storage\Values\StoredFileIdentifier;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AttachmentSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Attachment::class;

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
            Str::make('category'),
            Str::make('type'),
            Str::make('mime'),
            Str::make('identifier')->extractUsing(function (Attachment $attachment) {
                return (string)StoredFileIdentifier::fromAttachment($attachment);
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
