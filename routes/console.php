<?php

use Illuminate\Support\Facades\Schedule;

Schedule::commandWithDynamicInterval(
    'backup:run --only-db',
    interval: config('backup.backup.schedule_interval'),
    intervalArgs: config('backup.backup.schedule_interval_args')
);
Schedule::command('check:model-status')->everyFifteenMinutes();
Schedule::command('filestorage:cleanup')->daily();
