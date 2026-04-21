<?php

namespace App\Http\Resources\Legacy;

use App\Models\Attachment;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Utils\ServiceLocatorTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
class AttachmentResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $storageService = $this->getServiceInstance(FileStorageService::class);

        return [
            'fileData' => [
                'uuid' => $this->uuid,
                'name' => $this->name,
                'category' => $this->category,
                'type' => $this->type,
                'mime' => $this->mime,
                'url' => $storageService->retrieve(
                    StoredFileIdentifier::fromAttachment($this->resource)
                )?->getUrl()
            ]
        ];
    }
}
