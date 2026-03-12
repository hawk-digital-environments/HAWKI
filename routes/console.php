<?php

use Illuminate\Support\Facades\Schedule;

// Automatic backups are disabled when running in a Docker container
// it makes more sense to do the backup directly on the host machine.
if (getenv('BACKUP_DISABLED') === false) {
    Schedule::commandWithDynamicInterval(
        'backup:run --only-db',
        interval: config('backup.backup.schedule_interval'),
        intervalArgs: config('backup.backup.schedule_interval_args')
    );
}

Schedule::command('ai:models:check-status')->everyFifteenMinutes();
Schedule::command('ai:tools:check-status ')->everyFifteenMinutes();
Schedule::command('filestorage:cleanup')->daily();
