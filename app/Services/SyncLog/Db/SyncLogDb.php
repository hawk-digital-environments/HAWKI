<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Db;


use App\Models\SyncLog;
use App\Models\User;
use App\Services\SyncLog\Value\IncrementalSyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

readonly class SyncLogDb
{
    public function upsert(SyncLog $record): void
    {
        SyncLog::upsert(
            array_merge(
                $record->getAttributes(),
                [
                    'updated_at' => $record->updated_at->toDateTimeString('microsecond')
                ]
            ),
            ['type', 'target_id', 'user_id', 'room_id'],
            ['action', 'updated_at']
        );
    }
    
    public function deleteOutdated(): void
    {
        SyncLog::query()->where('updated_at', '<', now()->subDays(30))->delete();
    }
    
    public function deleteAllForUser(User $user): void
    {
        SyncLog::query()
            ->where('user_id', $user->id)
            ->orWhere(function (Builder $query) use ($user) {
                $query
                    ->where('type', SyncLogEntryType::USER)
                    ->where('target_id', $user->id);
            })
            ->delete();
    }
    
    /**
     * @param IncrementalSyncLogEntryConstraints $constraints
     * @return Collection<SyncLog>|null
     */
    public function findForIncrementalSync(IncrementalSyncLogEntryConstraints $constraints): Collection|null
    {
        $recordQuery = SyncLog::query();
        
        if ($constraints->allowedTypes !== null) {
            $recordQuery->whereIn('type', $constraints->allowedTypes);
        }
        
        $recordQuery->where(function (Builder $query) use ($constraints) {
            $query->where('user_id', $constraints->user->id);
            $query->orWhere('user_id', null);
        });
        
        if ($constraints->roomId !== null) {
            $recordQuery->where('room_id', $constraints->roomId);
        }
        
        if ($constraints->lastSync) {
            // If the given timestamp is set, but we do not have any records OLDER than the request,
            // we assume an outdated client; this would lead to an incomplete sync. So we return null
            // This means a full sync is required: See: \App\Services\SyncLogEntries\FullSyncLogGenerator
            $oldestRecord = $recordQuery->clone()->oldest('updated_at')->first(['updated_at']);
            if ($oldestRecord && $oldestRecord->updated_at > $constraints->lastSync) {
                return null;
            }
            $recordQuery->where('updated_at', '>', $constraints->lastSync->toDateTimeString('microsecond'));
        } else {
            return null;
        }
        
        if ($constraints->offset) {
            $recordQuery = $recordQuery->offset($constraints->offset);
        }
        
        if ($constraints->limit) {
            $recordQuery = $recordQuery->limit($constraints->limit);
        }
        
        return $recordQuery
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
