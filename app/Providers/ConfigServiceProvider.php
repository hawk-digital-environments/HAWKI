<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only run after application is fully booted
        try {
            // Mapping of database keys to config keys
            $configMappings = [
                'APP_NAME' => 'app.name',
                'APP_ENV' => 'app.env',
                'APP_DEBUG' => 'app.debug',
                'APP_URL' => 'app.url',
                'APP_TIMEZONE' => 'app.timezone',
                'APP_LOCALE' => 'app.locale',
                'AI_MENTION_HANDLE' => 'app.aiHandle',
                'ALLOW_EXTERNAL_COMMUNICATION' => 'sanctum.allow_external_communication',
            ];
            
            // Get all the settings in one query
            $settings = DB::table('app_settings')
                ->whereIn('key', array_keys($configMappings))
                ->get();
            
            foreach ($settings as $setting) {
                if (isset($configMappings[$setting->key])) {
                    $configKey = $configMappings[$setting->key];
                    $value = $setting->value;
                    
                    // Special handling for boolean values
                    if ($setting->key === 'APP_DEBUG') {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }
                    
                    // Special handling for aiHandle to prepend '@'
                    if ($setting->key === 'AI_MENTION_HANDLE') {
                        $value = '@' . $value;
                    }
                    
                    Config::set($configKey, $value);
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database isn't available yet
            // (like during migrations)
        }
    }
}
