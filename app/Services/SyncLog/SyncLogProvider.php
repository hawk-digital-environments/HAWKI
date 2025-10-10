<?php
declare(strict_types=1);


namespace App\Services\SyncLog;


use App\Http\Resources\SyncLogEntryCollection;
use App\Services\SyncLog\Generator\FullSyncLogGenerator;
use App\Services\SyncLog\Generator\IncrementalSyncLogGenerator;
use App\Services\SyncLog\Value\IncrementalSyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogType;

readonly class SyncLogProvider
{
    public function __construct(
        protected IncrementalSyncLogGenerator $incrementalGenerator,
        protected FullSyncLogGenerator        $fullGenerator,
    )
    {
    }
    
    public function getLog(IncrementalSyncLogEntryConstraints $constraints): SyncLogEntryCollection
    {
        $type = SyncLogType::INCREMENTAL;
        $entries = $this->incrementalGenerator->findEntries($constraints);

        if ($entries === null) {
            $type = SyncLogType::FULL;
            $entries = $this->fullGenerator->findEntries($constraints);
        }

        return new SyncLogEntryCollection(
            $type,
            $entries
        );
    }
}
