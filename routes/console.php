<?php

use Illuminate\Support\Facades\Schedule;

$backupInterval = config('backup.backup.schedule_interval');
Schedule::command('backup:run --only-db')->$backupInterval();
Schedule::command('check:model-status')->everyFifteenMinutes();
Schedule::command('filestorage:cleanup')->daily();
