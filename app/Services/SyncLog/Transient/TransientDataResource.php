<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Transient;


use Illuminate\Http\Resources\Json\JsonResource;

class TransientDataResource extends JsonResource
{
    /**
     * @var array
     */
    public $resource;
    
    public function toArray($request): array
    {
        return $this->resource;
    }
}
