<?php
declare(strict_types=1);


namespace App\Http\Resources;


use App\Models\UserKeychainValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserKeychainValueResource extends JsonResource
{
    /**
     * @var UserKeychainValue
     */
    public $resource;
    
    /**
     * @inheritDoc
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'value' => (string)$this->resource->value,
            'type' => $this->resource->type->value,
        ];
    }
}
