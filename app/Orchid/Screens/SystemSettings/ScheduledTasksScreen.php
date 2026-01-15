<?php

namespace App\Orchid\Screens\SystemSettings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\ScheduleListLayout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ScheduledTasksScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $schedules = $this->getScheduledTasks();

        // Sort by next_run_timestamp by default
        usort($schedules, function ($a, $b) {
            return ($a['next_run_timestamp'] ?? PHP_INT_MAX) <=> ($b['next_run_timestamp'] ?? PHP_INT_MAX);
        });

        return [
            'schedules' => $schedules,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return __('Scheduled Tasks');
    }

    /**
     * The description of the screen.
     */
    public function description(): ?string
    {
        return __('Manage and monitor all scheduled background tasks');
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            \App\Orchid\Layouts\Settings\SystemSettingsTabMenu::class,
            ScheduleListLayout::class,
        ];
    }

    /**
     * Get scheduled tasks information
     */
    private function getScheduledTasks(): array
    {
        try {
            // Run artisan schedule:list and parse output
            \Artisan::call('schedule:list');
            $output = \Artisan::output();

            $schedules = [];
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                // Skip header and empty lines
                if (empty(trim($line)) || str_contains($line, '─')) {
                    continue;
                }

                // Parse schedule line
                // Format: "  0 0 * * *  php artisan filestorage:cleanup ....... Next Due: in 10 Stunden"
                if (preg_match('/^\s*(.+?)\s+(php artisan .+?)\s+\.+\s+Next Due:\s+(.+)$/', $line, $matches)) {
                    $expression = trim($matches[1]);
                    // Remove potential quotes from expression
                    $expression = trim($expression, '"\'');

                    $command = trim($matches[2]);
                    $nextDue = trim($matches[3]);

                    // Convert to human readable
                    $expressionHuman = $this->cronToHuman($expression);

                    // Extract command name for description
                    $description = '';
                    $taskKey = null;

                    if (str_contains($command, 'backup:run')) {
                        $description = 'Database Backup';
                        $taskKey = 'backup.run';
                    } elseif (str_contains($command, 'backup:clean')) {
                        $description = 'Backup Cleanup';
                        $taskKey = 'backup.clean';
                    } elseif (str_contains($command, 'check:model-status')) {
                        $description = 'Model Status Check';
                        $taskKey = 'check.model-status';
                    } elseif (str_contains($command, 'filestorage:cleanup')) {
                        $description = 'File Storage Cleanup';
                        $taskKey = 'filestorage.cleanup';
                    }

                    // Check if task is enabled
                    $isEnabled = $this->isScheduleEnabled($taskKey);

                    // Calculate next run timestamp for sorting
                    $nextRunTimestamp = $this->calculateNextRun($expression);

                    $schedules[] = [
                        'expression' => $expression,
                        'expression_human' => $expressionHuman,
                        'command' => str_replace('php artisan ', '', $command),
                        'next_due' => $nextDue,
                        'next_run_timestamp' => $nextRunTimestamp,
                        'description' => $description,
                        'task_key' => $taskKey,
                        'is_enabled' => $isEnabled,
                    ];
                }
            }

            return $schedules;
        } catch (\Exception $e) {
            \Log::error('Schedule parsing failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Calculate next run timestamp from cron expression
     */
    private function calculateNextRun(string $expression): int
    {
        try {
            // Normalize whitespace
            $expression = preg_replace('/\s+/', ' ', trim($expression));
            $parts = explode(' ', $expression);

            if (count($parts) !== 5) {
                return time() + 86400; // Default to 1 day from now
            }

            [$minute, $hour, $day, $month, $weekday] = array_map('trim', $parts);

            $now = time();
            $currentMinute = (int) date('i', $now);
            $currentHour = (int) date('H', $now);

            // Every minute: * * * * *
            if ($minute === '*' && $hour === '*') {
                return $now + 60; // Next minute
            }

            // Daily at specific time
            if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && $weekday === '*') {
                $targetHour = (int) $hour;
                $targetMinute = (int) $minute;

                // Calculate seconds until target time today
                $targetTime = strtotime("today {$targetHour}:{$targetMinute}:00");

                // If target time has passed today, use tomorrow
                if ($targetTime <= $now) {
                    $targetTime = strtotime("tomorrow {$targetHour}:{$targetMinute}:00");
                }

                return $targetTime;
            }

            // Default: assume it runs tomorrow
            return $now + 86400;
        } catch (\Exception $e) {
            return time() + 86400; // Default to 1 day from now
        }
    }

    /**
     * Convert cron expression to human readable format
     */
    private function cronToHuman(string $expression): string
    {
        // Normalize whitespace: replace multiple spaces with single space
        $expression = preg_replace('/\s+/', ' ', trim($expression));

        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            return $expression;
        }

        [$minute, $hour, $day, $month, $weekday] = array_map('trim', $parts);

        // Every minute: * * * * *
        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Jede Minute';
        }

        // Every X minutes: */5 * * * *
        if (preg_match('/^\*\/(\d+)$/', $minute, $matches) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Alle '.$matches[1].' Minuten';
        }

        // Hourly: 30 * * * *
        if ($minute !== '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Stündlich (Minute '.$minute.')';
        }

        // Daily at specific time: 37 13 * * *
        if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && $weekday === '*') {
            $hourInt = (int) $hour;
            $minuteInt = (int) $minute;

            return 'Täglich um '.sprintf('%02d:%02d', $hourInt, $minuteInt).' Uhr';
        }

        // Weekly (specific day): 0 2 * * 0
        if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && is_numeric($weekday)) {
            $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            $weekdayInt = (int) $weekday;
            $dayName = $days[$weekdayInt] ?? 'Unbekannt';
            $hourInt = (int) $hour;
            $minuteInt = (int) $minute;

            return $dayName.' um '.sprintf('%02d:%02d', $hourInt, $minuteInt).' Uhr';
        }

        // Monthly (specific day): 0 2 1 * *
        if (is_numeric($minute) && is_numeric($hour) && is_numeric($day) && $month === '*' && $weekday === '*') {
            $hourInt = (int) $hour;
            $minuteInt = (int) $minute;

            return 'Monatlich am '.$day.'. um '.sprintf('%02d:%02d', $hourInt, $minuteInt).' Uhr';
        }

        // Yearly (specific date): 0 2 1 6 *
        if (is_numeric($minute) && is_numeric($hour) && is_numeric($day) && is_numeric($month) && $weekday === '*') {
            $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
            $monthInt = (int) $month;
            $monthName = $months[$monthInt] ?? $month;
            $hourInt = (int) $hour;
            $minuteInt = (int) $minute;

            return 'Jährlich am '.$day.'. '.$monthName.' um '.sprintf('%02d:%02d', $hourInt, $minuteInt).' Uhr';
        }

        // Fallback
        return $expression;
    }

    /**
     * Check if a scheduled task is enabled
     */
    private function isScheduleEnabled(?string $taskKey): bool
    {
        if (! $taskKey) {
            return true;
        }

        // Map task keys to config keys
        $configMap = [
            'backup.run' => 'scheduler.backup.enabled',
            'backup.clean' => 'scheduler.cleanup.enabled',
            'check.model-status' => 'scheduler.model_status_check.enabled',
            'filestorage.cleanup' => 'scheduler.filestorage_cleanup.enabled',
        ];

        $configKey = $configMap[$taskKey] ?? null;

        if (! $configKey) {
            return true; // No config, always enabled
        }

        return config($configKey, true);
    }

    /**
     * Toggle schedule enabled/disabled
     */
    public function toggleSchedule(string $task, string $action)
    {
        // Map task keys to settings keys
        $settingsMap = [
            'backup.run' => 'scheduler_backup.enabled',
            'backup.clean' => 'scheduler_cleanup.enabled',
            'check.model-status' => 'scheduler_model_status_check.enabled',
            'filestorage.cleanup' => 'scheduler_filestorage_cleanup.enabled',
        ];

        $settingKey = $settingsMap[$task] ?? null;

        if (! $settingKey) {
            Toast::error(__('This task cannot be toggled'));

            return redirect()->route('platform.systems.settings.scheduled-tasks');
        }

        $setting = AppSetting::where('key', $settingKey)->first();

        if (! $setting) {
            Toast::error(__('Setting not found'));

            return redirect()->route('platform.systems.settings.scheduled-tasks');
        }

        $newValue = $action === 'enable' ? 'true' : 'false';
        $setting->value = $newValue;
        $setting->save();

        // Clear config cache
        \Artisan::call('config:clear');

        $message = $action === 'enable'
            ? __('Task activated successfully')
            : __('Task deactivated successfully');

        Toast::success($message);

        return redirect()->route('platform.systems.settings.scheduled-tasks');
    }

    /**
     * Permission
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
