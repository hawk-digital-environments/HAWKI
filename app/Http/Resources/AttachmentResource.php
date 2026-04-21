<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * @var Attachment
     */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'category' => $this->resource->category,
            'type' => $this->resource->type,
            'mime' => $this->resource->mime,
            'identifier' => (string)StoredFileIdentifier::fromAttachment($this->resource)
        ];
    }
}
