<?php

namespace App\Orchid\Screens\SystemSettings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\BackupListLayout;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BackupSettingsScreen extends Screen
{
    use OrchidSettingsManagementTrait;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $settings = AppSetting::whereIn('group', ['backup', 'system'])->get();

        $fields = [];
        foreach ($settings as $setting) {
            $fields[$setting->key] = $setting->value;
        }

        // Get list of recent backups
        $backupPath = storage_path('app/HAWKI2');
        $backups = [];

        if (is_dir($backupPath)) {
            $files = glob($backupPath.'/*.zip');

            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'size_human' => $this->formatBytes(filesize($file)),
                    'created_at' => filemtime($file),
                    'created_human' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }

            // Sort by creation time, newest first
            usort($backups, function ($a, $b) {
                return $b['created_at'] <=> $a['created_at'];
            });
        }

        // Convert to paginator for Orchid Table
        $collection = collect($backups);

        $perPage = 10;
        $currentPage = request()->get('page', 1);
        $currentItems = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $fields['backups'] = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $fields;
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

                    $schedules[] = [
                        'expression' => $expression,
                        'expression_human' => $expressionHuman,
                        'command' => str_replace('php artisan ', '', $command),
                        'next_due' => $nextDue,
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

            return redirect()->route('platform.systems.settings.backup');
        }

        $setting = AppSetting::where('key', $settingKey)->first();

        if (! $setting) {
            Toast::error(__('Setting not found'));

            return redirect()->route('platform.systems.settings.backup');
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

        return redirect()->route('platform.systems.settings.backup');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return __('Backup Configuration');
    }

    /**
     * The description of the screen.
     */
    public function description(): ?string
    {
        return __('Configure backup settings including schedule, retention policy, and filename prefix');
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make(__('Save'))
                ->icon('bs.check-circle')
                ->method('saveSettings')
                ->canSee(\Auth::user()->hasAccess('platform.systems.settings')),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $settings = AppSetting::whereIn('group', ['backup', 'system'])->get()->keyBy('key');
        $backupEnabled = $settings['scheduler_backup.enabled']->typed_value ?? true;
        $cleanupEnabled = $settings['scheduler_cleanup.enabled']->typed_value ?? false;

        // Build backup schedule fields
        $scheduleFields = [
            Switcher::make('settings.scheduler_backup__enabled')
                ->title('Enable Automatic Backups')
                ->sendTrueOrFalse()
                ->value($settings['scheduler_backup.enabled']->typed_value ?? true)
                ->help('Enable or disable automatic database backups'),
        ];

        // Only add schedule fields if backups are enabled
        if ($backupEnabled) {
            $scheduleFields[] = Select::make('settings.scheduler_backup.schedule_interval')
                ->title('Backup Schedule Interval')
                ->options([
                    'everyMinute' => 'Every Minute (Testing only!)',
                    'everyFiveMinutes' => 'Every 5 Minutes',
                    'everyTenMinutes' => 'Every 10 Minutes',
                    'everyFifteenMinutes' => 'Every 15 Minutes',
                    'everyThirtyMinutes' => 'Every 30 Minutes',
                    'hourly' => 'Hourly',
                    'daily' => 'Daily',
                    'weekly' => 'Weekly (Recommended)',
                    'monthly' => 'Monthly',
                ])
                ->value($settings['scheduler_backup.schedule_interval']->value ?? 'weekly')
                ->help('How often automatic backups should run');

            $scheduleFields[] = Input::make('settings.scheduler_backup.schedule_time')
                ->title('Backup Time')
                ->type('time')
                ->value($settings['scheduler_backup.schedule_time']->value ?? '02:00')
                ->help('Time when backups should run (24-hour format)');
        }

        // Build retention fields array
        $retentionFields = [
            Switcher::make('settings.scheduler_cleanup__enabled')
                ->title('Enable Automatic Backup Cleanup')
                ->sendTrueOrFalse()
                ->value($settings['scheduler_cleanup.enabled']->typed_value ?? false)
                ->help('WARNING: When enabled, old backups will be deleted according to the retention policy below'),
        ];

        // Only add retention policy fields if cleanup is enabled
        if ($cleanupEnabled) {
            $retentionFields = array_merge($retentionFields, [
                Input::make('settings.backup_cleanup.default_strategy.keep_all_backups_for_days')
                    ->title('Keep All Backups For Days')
                    ->type('number')
                    ->min(1)
                    ->value($settings['backup_cleanup.default_strategy.keep_all_backups_for_days']->value ?? 7)
                    ->help('Keep all backups for this many days (minimum 1 day)'),

                Input::make('settings.backup_cleanup.default_strategy.keep_daily_backups_for_days')
                    ->title('Keep Daily Backups For Days')
                    ->type('number')
                    ->min(0)
                    ->value($settings['backup_cleanup.default_strategy.keep_daily_backups_for_days']->value ?? 16)
                    ->help('After the initial period, keep one backup per day for this many days'),

                Input::make('settings.backup_cleanup.default_strategy.keep_weekly_backups_for_weeks')
                    ->title('Keep Weekly Backups For Weeks')
                    ->type('number')
                    ->min(0)
                    ->value($settings['backup_cleanup.default_strategy.keep_weekly_backups_for_weeks']->value ?? 8)
                    ->help('After daily period, keep one backup per week for this many weeks'),

                Input::make('settings.backup_cleanup.default_strategy.keep_monthly_backups_for_months')
                    ->title('Keep Monthly Backups For Months')
                    ->type('number')
                    ->min(0)
                    ->value($settings['backup_cleanup.default_strategy.keep_monthly_backups_for_months']->value ?? 4)
                    ->help('After weekly period, keep one backup per month for this many months'),

                Input::make('settings.backup_cleanup.default_strategy.keep_yearly_backups_for_years')
                    ->title('Keep Yearly Backups For Years')
                    ->type('number')
                    ->min(0)
                    ->value($settings['backup_cleanup.default_strategy.keep_yearly_backups_for_years']->value ?? 2)
                    ->help('After monthly period, keep one backup per year for this many years'),

                Input::make('settings.backup_cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than')
                    ->title('Maximum Storage (MB)')
                    ->type('number')
                    ->min(100)
                    ->value($settings['backup_cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than']->value ?? 5000)
                    ->help('Delete oldest backups when total size exceeds this limit (minimum 100 MB)'),
            ]);
        }

        return [
            \App\Orchid\Layouts\Settings\SystemSettingsTabMenu::class,

            Layout::block(Layout::rows([
                Input::make('settings.scheduler_backup.destination.filename_prefix')
                    ->title('Backup Filename Prefix')
                    ->placeholder('e.g., prod-, staging-')
                    ->value($settings['scheduler_backup.destination.filename_prefix']->value ?? '')
                    ->help('Prefix for backup filenames'),

                Switcher::make('settings.scheduler_backup.include_files')
                    ->title('Include Files in Backup')
                    ->sendTrueOrFalse()
                    ->value($settings['scheduler_backup.include_files']->typed_value ?? false)
                    ->help('When enabled, backup will include database + user files (attachments, avatars, .env). When disabled, only database will be backed up (--only-db flag).'),
            ]))
                ->title(__('General Settings'))
                ->description(__('Configure general backup settings like filename prefix')),

            Layout::block(Layout::rows($scheduleFields))
                ->title(__('Backup Schedule'))
                ->description($backupEnabled
                    ? __('Configure when backups should run automatically')
                    : __('Enable automatic backups to configure schedule'))
                ->commands([
                    Button::make(__('Run Backup Now'))
                        ->icon('bs.database-fill-add')
                        ->method('runBackupNow')
                        ->confirm(__('Create a database backup now?'))
                        ->canSee(\Auth::user()->hasAccess('platform.systems.settings')),
                ]),

            Layout::block(Layout::rows($retentionFields))
                ->title(__('Retention Policy'))
                ->description($cleanupEnabled
                    ? __('Configure how long backups should be kept')
                    : __('Enable automatic cleanup to configure retention policy'))
                ->commands($cleanupEnabled ? [
                    Button::make(__('Run Cleanup Now'))
                        ->icon('bs.trash')
                        ->method('runCleanupNow')
                        ->confirm(__('This will delete old backups according to the retention policy. Are you sure?'))
                        ->canSee(\Auth::user()->hasAccess('platform.systems.settings')),
                ] : []),

            Layout::block(BackupListLayout::class)
                ->title(__('Recent Backups'))
                ->description(__('List of the most recent database backups')),

            Layout::modal('restoreBackupModal', [
                \App\Orchid\Layouts\Settings\RestoreBackupModal::class,
            ])
                ->title('⚠️ Confirm Database Restore')
                ->applyButton('Restore Database')
                ->closeButton('Cancel'),
        ];
    }

    /**
     * Run backup now
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function runBackupNow()
    {
        try {
            // Check if files should be included in backup
            $includeFiles = config('scheduler.backup.include_files', false);

            // Run backup with appropriate flags
            if ($includeFiles) {
                // Include database + files
                \Artisan::call('backup:run');
            } else {
                // Database only
                \Artisan::call('backup:run', ['--only-db' => true]);
            }

            $output = \Artisan::output();

            $backupType = $includeFiles ? 'Database + Files' : 'Database only';
            Toast::success(__('Backup created successfully!'));

            // Send persistent notification to admin
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '✅ Backup Created',
                $backupType.' backup created successfully at '.now()->format('Y-m-d H:i:s'),
                'success'
            ));

            return redirect()->route('platform.systems.settings.backup')
                ->with('info', $output);
        } catch (\Exception $e) {
            Toast::error(__('Backup failed: :message', ['message' => $e->getMessage()]));

            // Send persistent error notification
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '❌ Backup Failed',
                'Backup failed: '.$e->getMessage(),
                'error'
            ));

            return redirect()->route('platform.systems.settings.backup');
        }
    }

    /**
     * Run cleanup now
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function runCleanupNow()
    {
        try {
            \Artisan::call('backup:clean');
            $output = \Artisan::output();

            Toast::success(__('Backup cleanup completed successfully!'));

            // Send persistent notification to admin
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '🧹 Cleanup Completed',
                'Backup cleanup completed successfully at '.now()->format('Y-m-d H:i:s'),
                'success'
            ));

            return redirect()->route('platform.systems.settings.backup')
                ->with('info', $output);
        } catch (\Exception $e) {
            Toast::error(__('Backup cleanup failed: :message', ['message' => $e->getMessage()]));

            // Send persistent error notification
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '❌ Cleanup Failed',
                'Backup cleanup failed: '.$e->getMessage(),
                'error'
            ));

            return redirect()->route('platform.systems.settings.backup');
        }
    }

    /**
     * Download backup file
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadBackup(string $filename)
    {
        $backupPath = storage_path('app/HAWKI2/'.$filename);

        if (! file_exists($backupPath)) {
            Toast::error(__('Backup file not found'));

            return redirect()->route('platform.systems.settings.backup');
        }

        return response()->download($backupPath);
    }

    /**
     * Delete backup file
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteBackup(Request $request)
    {
        $filename = $request->get('filename');

        if (empty($filename)) {
            Toast::error(__('No filename provided'));

            return redirect()->route('platform.systems.settings.backup');
        }

        // Sanitize filename to prevent path traversal
        $filename = basename($filename);
        $backupPath = storage_path('app/HAWKI2/'.$filename);

        if (! file_exists($backupPath)) {
            Toast::error(__('Backup file not found'));

            return redirect()->route('platform.systems.settings.backup');
        }

        try {
            unlink($backupPath);
            Toast::success(__('Backup deleted successfully'));

            // Send persistent notification to admin
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '🗑️ Backup Deleted',
                'Backup deleted: '.$filename.' at '.now()->format('Y-m-d H:i:s'),
                'success'
            ));
        } catch (\Exception $e) {
            Toast::error(__('Failed to delete backup: :message', ['message' => $e->getMessage()]));

            // Send persistent error notification
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '❌ Delete Failed',
                'Failed to delete backup '.$filename.': '.$e->getMessage(),
                'error'
            ));
        }

        return redirect()->route('platform.systems.settings.backup');
    }

    /**
     * Restore backup file
     */
    public function restoreBackup(Request $request)
    {
        // Validate password
        $password = $request->input('admin_password');
        $filename = $request->input('filename');

        if (! $password) {
            Toast::error(__('Password is required'));

            return redirect()->route('platform.systems.settings.backup');
        }

        // Check if password is correct for current user
        if (! \Hash::check($password, \Auth::user()->password)) {
            Toast::error(__('Incorrect password'));

            return redirect()->route('platform.systems.settings.backup');
        }

        // Sanitize filename
        $filename = basename($filename);
        $backupPath = storage_path('app/HAWKI2/'.$filename);

        if (! file_exists($backupPath)) {
            Toast::error(__('Backup file not found'));

            return redirect()->route('platform.systems.settings.backup');
        }

        try {
            // Step 1: Enter maintenance mode
            \Artisan::call('down', [
                '--secret' => config('app.key'),
                '--render' => 'errors::503',
            ]);

            // Step 2: Extract SQL from ZIP
            $zip = new \ZipArchive;
            if ($zip->open($backupPath) !== true) {
                \Artisan::call('up');
                Toast::error(__('Failed to open backup file'));

                return redirect()->route('platform.systems.settings.backup');
            }

            // Find the SQL file in the ZIP
            $sqlFile = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (str_ends_with($filename, '.sql')) {
                    $sqlFile = $filename;
                    break;
                }
            }

            if (! $sqlFile) {
                $zip->close();
                \Artisan::call('up');
                Toast::error(__('No SQL file found in backup'));

                return redirect()->route('platform.systems.settings.backup');
            }

            // Extract SQL file to temp location
            $tempDir = storage_path('app/temp_restore');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zip->extractTo($tempDir, $sqlFile);
            $zip->close();

            $sqlPath = $tempDir.'/'.$sqlFile;

            // Step 4: Restore database
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port', 3306);

            // Build mysql command
            $command = sprintf(
                'mysql -h %s -P %d -u %s -p%s %s < %s 2>&1',
                escapeshellarg($host),
                $port,
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($sqlPath)
            );

            // Execute restore
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Cleanup temp files recursively
            $this->removeDirectory($tempDir);

            // Step 2: Exit maintenance mode
            \Artisan::call('up');

            if ($returnVar !== 0) {
                \Log::error('Backup restore failed', [
                    'return_code' => $returnVar,
                    'output' => implode("\n", $output),
                ]);
                Toast::error(__('Database restore failed. Please check logs.'));

                return redirect()->route('platform.systems.settings.backup');
            }

            // Step 3: Clear all caches
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('view:clear');

            Toast::success(__('Database restored successfully! Please log in again.'));

            // Send persistent notification to admin (before logout)
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '✅ Database Restored',
                'Database restored successfully from backup: '.basename($filename).' at '.now()->format('Y-m-d H:i:s'),
                'success'
            ));

            // Logout user as session data might be invalid
            \Auth::logout();

            return redirect()->route('platform.login');

        } catch (\Exception $e) {
            // Make sure we exit maintenance mode
            try {
                \Artisan::call('up');
            } catch (\Exception $upException) {
                \Log::error('Failed to exit maintenance mode', ['error' => $upException->getMessage()]);
            }

            \Log::error('Backup restore failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Toast::error(__('Restore failed: :message', ['message' => $e->getMessage()]));

            // Send persistent error notification
            \Auth::user()->notify(new \App\Notifications\BackupOperationNotification(
                '❌ Restore Failed',
                'Database restore failed: '.$e->getMessage().' at '.now()->format('Y-m-d H:i:s'),
                'error'
            ));

            return redirect()->route('platform.systems.settings.backup');
        }
    }

    /**
     * Recursively remove a directory and its contents
     */
    private function removeDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Apply filter
     */
    public function applyFilter(Request $request)
    {
        return redirect()->route('platform.systems.settings.backup', [
            'date_filter' => $request->input('date_filter'),
        ]);
    }

    /**
     * Clear filter
     */
    public function clearFilter()
    {
        return redirect()->route('platform.systems.settings.backup');
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
