<?php
declare(strict_types=1);


namespace App\Http\Resources;


use App\Services\SyncLog\Value\SyncLogEntryActionEnum;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncLogEntryResource extends JsonResource
{
    /**
     * @var JsonResource
     */
    public $resource;

    public function __construct(
        ?JsonResource                    $resource,
        protected int                    $resourceId,
        protected SyncLogEntryTypeEnum   $type,
        protected SyncLogEntryActionEnum $action,
        protected Carbon                 $timestamp,
        protected int                    $userId
    )
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'action' => $this->action->value,
            'timestamp' => $this->timestamp,
            'resource' => $this->resource,
            'resource_id' => $this->resourceId,
        ];
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
