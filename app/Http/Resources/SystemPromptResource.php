<?php
declare(strict_types=1);


namespace App\Http\Resources;


use App\Services\AI\Value\SystemPrompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemPromptResource extends JsonResource
{
    /**
     * @var SystemPrompt
     */
    public $resource;
    
    /**
     * @inheritDoc
     */
    public function toArray(Request $request): array
    {
        return $this->resource->jsonSerialize();
    }
}
