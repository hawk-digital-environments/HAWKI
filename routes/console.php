<?php

use Illuminate\Support\Facades\Schedule;

$interval = env('DB_BACKUP_INTERVAL');
if(!empty($interval))   Schedule::command('backup:run --only-db')->$interval();
