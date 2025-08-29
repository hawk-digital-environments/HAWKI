<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Db;


use App\Models\SyncLog;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use Illuminate\Support\Collection;

readonly class SyncLogDb
{
    public function upsert(SyncLog $record): void
    {
        SyncLog::upsert(
            $record->getAttributes(),
            ['type', 'target_id', 'user_id'],
            ['action', 'updated_at']
        );
    }
    
    public function deleteOutdated(): void
    {
        SyncLog::query()->where('updated_at', '<', now()->subDays(30))->delete();
    }
    
    /**
     * @param SyncLogEntryConstraints $constraints
     * @return Collection<SyncLog>|null
     */
    public function findForIncrementalSync(SyncLogEntryConstraints $constraints): Collection|null
    {
        $recordQuery = SyncLog::query();
        
        $recordQuery->where('user_id', $constraints->user->id);
        
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
            // I use >= here, to make sure there is no overlap that might have happened in the same second.
            // Yes, this leads to syncing one record more than necessary, but this is a trade-off.
            $recordQuery->where('updated_at', '>=', $constraints->lastSync);
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
