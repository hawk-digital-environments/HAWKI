<?php
declare(strict_types=1);


namespace App\Http\Resources;


use App\Services\SyncLog\Value\SyncLogEntryAction;
use App\Services\SyncLog\Value\SyncLogEntryType;
use App\Services\SyncLog\Value\SyncLogType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncLogEntryResource extends JsonResource
{
    /**
     * @var string
     */
    public $resource;
    
    public function __construct(
        // The "true" hack is used to indicate that the resource is not available (e.g. deleted)
        // This is required, because if we use null, laravel will simply dump this entry as null
        // instead of calling toArray() method
        JsonResource|true         $resource,
        int                       $resourceId,
        SyncLogEntryType          $type,
        SyncLogEntryAction        $action,
        Carbon                    $timestamp,
        private readonly int|null $userId
    )
    {
        // We MUST encode the resource as JSON here, because otherwise we loose the context from
        // which it was created (e.g. ExtAppContext), we also directly serialize dates and child
        // resources to ensure they are properly represented in the final JSON
        parent::__construct(json_encode([
            'type' => $type->value,
            'action' => $action->value,
            'timestamp' => $timestamp,
            'resource' => $resource === true ? null : $resource,
            'resource_id' => $resourceId,
        ], JSON_THROW_ON_ERROR));
    }
    
    public function toArray(?Request $request = null): array
    {
        return json_decode($this->resource, true, 512, JSON_THROW_ON_ERROR);
    }
    
    public function getUserId(): int|null
    {
        return $this->userId;
    }
    
    /**
     * @inheritDoc
     */
    protected static function newCollection($resource): SyncLogEntryCollection
    {
        return new SyncLogEntryCollection(SyncLogType::INCREMENTAL, $resource);
    }
}
