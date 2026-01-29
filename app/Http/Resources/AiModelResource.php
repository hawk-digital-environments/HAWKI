<?php

namespace App\Http\Resources;

use App\Services\AI\Value\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiModelResource extends JsonResource
{
    /**
     * @var AiModel
     */
    public $resource;
    
    public function toArray(Request $request): array
    {
        $raw = $this->resource->toArray();
        
        // "id" is a reserved field in the frontend resources, so we rename it to "model_id"
        $raw['model_id'] = $raw['id'];
        unset($raw['id']);
        
        return $raw;
    }
}
