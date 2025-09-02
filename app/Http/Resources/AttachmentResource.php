<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Services\Storage\FileStorageService;
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
        $fileStorage = app(FileStorageService::class);
        
        return [
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'category' => $this->resource->category,
            'type' => $this->resource->type,
            'mime' => $this->resource->mime,
            'url' => $fileStorage->getFileUrl(
                uuid: $this->resource->uuid,
                category: $this->resource->category
            )
        ];
    }
}
