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
Schedule::command('passkey-backups:cleanup-archive')->daily();

\Illuminate\Support\Facades\Schedule::call(fn() => \Illuminate\Support\Facades\Cache::put('admin.scheduler.last_seen', now()->toIso8601String(), 300))->everyMinute();
\Illuminate\Support\Facades\Schedule::command('usage:summarize-monthly')->monthly()->withoutOverlapping();
