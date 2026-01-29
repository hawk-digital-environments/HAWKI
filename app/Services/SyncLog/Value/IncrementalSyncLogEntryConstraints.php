<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Value;


use App\Models\User;
use Carbon\Carbon;

readonly class IncrementalSyncLogEntryConstraints extends SyncLogEntryConstraints
{
    public function __construct(
        User           $user,
        public ?Carbon $lastSync = null,
        ?int           $offset = null,
        ?int           $limit = null,
        ?int           $roomId = null,
        public ?array  $allowedTypes = null,
    )
    {
        parent::__construct(
            user: $user,
            offset: $offset,
            limit: $limit,
            roomId: $roomId
        );
    }
    
    public static function addAllowedTypes(IncrementalSyncLogEntryConstraints $constraints, ?array $allowedTypes = null): self
    {
        return new self(
            user: $constraints->user,
            lastSync: $constraints->lastSync,
            offset: $constraints->offset,
            limit: $constraints->limit,
            roomId: $constraints->roomId,
            allowedTypes: $allowedTypes,
        );
    }
}
