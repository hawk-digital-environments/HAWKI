<?php
declare(strict_types=1);


namespace App\Http\Resources;


use App\Services\Storage\Value\StorageFileInfo;
use Illuminate\Http\Resources\Json\JsonResource;

class StorageFileProxyRoutePathArgsResource extends JsonResource
{
    /**
     * @var StorageFileInfo|null
     */
    public $resource;
    
    public function toArray($request): array
    {
        if ($this->resource === null) {
            return [];
        }
        
        return [
            'category' => $this->resource->category->value,
            'filename' => $this->resource->basename // The basename IS the uuid with extension
        ];
    }
}
