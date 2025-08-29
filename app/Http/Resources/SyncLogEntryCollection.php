<?php

namespace App\Http\Resources;

use App\Services\SyncLog\Value\SyncLogTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SyncLogEntryCollection extends ResourceCollection
{
    protected SyncLogTypeEnum $type;
    public $collects = SyncLogEntryResource::class;

    public function __construct(SyncLogTypeEnum $type, $resource)
    {
        parent::__construct($resource);
        $this->type = $type;
    }

    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'log' => $this->collection,
        ];
    }
}
