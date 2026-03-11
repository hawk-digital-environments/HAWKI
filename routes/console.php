<?php

use Illuminate\Support\Facades\Schedule;

$backupInterval = config('backup.backup.schedule_interval');
if (strtolower($backupInterval) !== 'never') {
    Schedule::command('backup:run --only-db')->$backupInterval(
        ...array_values(
            config('backup.backup.schedule_interval_data')
        )
    );
}
Schedule::command('check:model-status')->everyFifteenMinutes();
Schedule::command('filestorage:cleanup')->daily();
