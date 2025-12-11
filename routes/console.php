<?php

use Illuminate\Support\Facades\Schedule;

// Backup runs at the configured interval and time if enabled in settings
$backupInterval = config('backup.backup.schedule_interval');
$backupTime = config('backup.backup.schedule_time', '02:00');
$includeFiles = config('backup.backup.include_files', false);

// Build backup command with appropriate flags
$backupCommand = $includeFiles ? 'backup:run' : 'backup:run --only-db';
$backupSchedule = Schedule::command($backupCommand);

// Apply interval
if (in_array($backupInterval, ['everyMinute', 'everyFiveMinutes', 'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes', 'hourly'])) {
    // For frequent intervals, don't apply time
    $backupSchedule->$backupInterval();
} else {
    // For daily, weekly, monthly - apply time
    $backupSchedule->$backupInterval()->at($backupTime);
}

// Only run if enabled
$backupSchedule->when(function () {
    return config('backup.backup.enabled') == true;
});

// Cleanup runs daily at 01:00 if enabled in settings
Schedule::command('backup:clean')
    ->daily()
    ->at('01:00')
    ->when(function () {
        return config('backup.cleanup.enabled') == true;
    });

// Model status check runs every minute if enabled
Schedule::command('check:model-status')
    ->everyMinute()
    ->when(function () {
        return config('system.schedule.model_status_check.enabled', true) == true;
    });

// File storage cleanup runs daily if enabled
Schedule::command('filestorage:cleanup')
    ->daily()
    ->when(function () {
        return config('system.schedule.filestorage_cleanup.enabled', true) == true;
    });
