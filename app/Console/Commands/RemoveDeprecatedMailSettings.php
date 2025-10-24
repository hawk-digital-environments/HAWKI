<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RemoveDeprecatedMailSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:remove-deprecated 
                            {keys?* : The setting keys to remove (space-separated)}
                            {--all : Remove all predefined deprecated settings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove deprecated settings from database';

    /**
     * Predefined list of deprecated settings
     *
     * @var array
     */
    protected $predefinedDeprecatedSettings = [
        'mail_mailers.smtp.url',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keys = $this->argument('keys');
        $removeAll = $this->option('all');

        // Determine which settings to remove
        if ($removeAll) {
            $settingsToRemove = $this->predefinedDeprecatedSettings;
            $this->info('Removing all predefined deprecated settings...');
        } elseif (!empty($keys)) {
            $settingsToRemove = $keys;
            $this->info('Removing specified settings...');
        } else {
            $this->error('No settings specified. Use --all or provide setting keys as arguments.');
            $this->line('');
            $this->line('Examples:');
            $this->line('  php artisan settings:remove-deprecated mail_mailers.smtp.url');
            $this->line('  php artisan settings:remove-deprecated key1 key2 key3');
            $this->line('  php artisan settings:remove-deprecated --all');
            return self::FAILURE;
        }

        $removedCount = 0;
        $notFoundCount = 0;

        foreach ($settingsToRemove as $settingKey) {
            $setting = AppSetting::where('key', $settingKey)->first();

            if ($setting) {
                $this->line("✓ Removing: {$settingKey}");
                $setting->delete();
                
                // Clear cache for this setting
                Cache::forget('app_settings_'.$settingKey);
                
                $removedCount++;
            } else {
                $this->line("○ Not found (already removed): {$settingKey}");
                $notFoundCount++;
            }
        }

        if ($removedCount > 0) {
            // Clear config cache
            $this->call('config:clear');
            Cache::flush();
            
            $this->newLine();
            $this->info("Successfully removed {$removedCount} setting(s).");
            if ($notFoundCount > 0) {
                $this->line("{$notFoundCount} setting(s) were already removed.");
            }
        } else {
            $this->info('No settings were removed (all already removed).');
        }

        return self::SUCCESS;
    }
}
