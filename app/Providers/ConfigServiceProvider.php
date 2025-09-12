<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Cache key for configuration overrides
     */
    const CONFIG_CACHE_KEY = 'app_settings_overrides';
    
    /**
     * Time in seconds for how long the configuration settings should be cached
     */
    const CACHE_TTL = 3600; // 1 hour
    
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
            // Check if required tables exist before proceeding
            if (!$this->requiredTablesExist()) {
                Log::info('Required tables do not exist yet. Skipping configuration override.');
                return;
            }
            
            // First, try to load the overridden configurations from cache
            $cachedOverrides = $this->getCachedOverrides();
            
            if ($cachedOverrides !== null) {
                // Cache hit: Use the cached overrides
                //Log::debug('Using cached configuration overrides');
                $this->applyOverrides($cachedOverrides);
                return;
            }
            
            // Cache miss: Load the configuration settings from settings.php
            $configSettings = config('settings');
            
            if (empty($configSettings)) {
                Log::info('No config settings defined in settings.php. Skipping config override from database.');
                return;
            }
            
            // Create a flat list of all overridable keys
            $overridableKeys = [];
            $dbKeysToLoad = [];
            
            foreach ($configSettings as $configFile => $keys) {
                // Skip the group mapping
                if ($configFile === 'group_mapping') {
                    continue;
                }
                
                // Check if the entry is an array (a valid key)
                if (!is_array($keys)) continue;
                
                foreach ($keys as $key => $value) {
                    // If the key is numeric, use the value as the key and no description
                    if (is_int($key)) {
                        $realKey = $value;
                    } else {
                        // Otherwise, the key is the key and the value is the description
                        $realKey = $key;
                    }
                    
                    // Create DB key (with underscore): app_name
                    $dbKey = "{$configFile}_{$realKey}";
                    // Create config key (with dot): app.name
                    $configKey = "{$configFile}.{$realKey}";
                    
                    $overridableKeys[$dbKey] = $configKey;
                    $dbKeysToLoad[] = $dbKey;
                }
            }
            
            if (empty($dbKeysToLoad)) {
                Log::info('No overridable keys found in settings.php. Skipping database load.');
                return;
            }

            //Log::debug("Loading settings from database for keys: " . implode(', ', $dbKeysToLoad));
            
            // Load all relevant settings from the database
            $settings = DB::table('app_settings')
                ->whereIn('key', $dbKeysToLoad)
                ->get();
            
            if ($settings->isEmpty()) {
                Log::info('No matching settings found in database for defined keys.');
                return;
            }
            
            // Create an array with the overrides for the cache
            $overrides = [];
            
            foreach ($settings as $setting) {
                // Check if the key is in the list of overridable keys
                if (isset($overridableKeys[$setting->key])) {
                    // Get the corresponding config key
                    $configKey = $overridableKeys[$setting->key];
                    $value = $setting->value;
                    
                    // Convert value based on the type stored in database
                    if ($value !== null) {
                        switch ($setting->type) {
                            case 'boolean':
                                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                                break;
                            case 'integer':
                                $value = (int) $value;
                                break;
                            case 'json':
                                $value = json_decode($value, true);
                                break;
                        }
                    }
                    
                    // Store the override for the cache
                    $overrides[$configKey] = $value;
                    
                    // Override the configuration value directly
                    Config::set($configKey, $value);
                    //Log::debug("Overriding config key {$configKey} with value from database");
                }
            }
            
            // Cache the overrides for future requests
            if (!empty($overrides)) {
                $this->cacheOverrides($overrides);
            }
            
        } catch (\Exception $e) {
            // Log the error but don't crash the application
            Log::error('Failed to load settings from database: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }
    
    /**
     * Gets the cached configuration overrides
     *
     * @return array|null The cached overrides or null if none in cache
     */
    private function getCachedOverrides()
    {
        return Cache::get(self::CONFIG_CACHE_KEY);
    }
    
    /**
     * Stores the configuration overrides in cache
     *
     * @param array $overrides The overrides to cache
     * @return void
     */
    private function cacheOverrides(array $overrides)
    {
        Cache::put(self::CONFIG_CACHE_KEY, $overrides, self::CACHE_TTL);
        //Log::debug('Cached ' . count($overrides) . ' configuration overrides for ' . self::CACHE_TTL . ' ms');
    }
    
    /**
     * Applies the cached overrides to the configuration
     *
     * @param array $overrides The overrides to apply
     * @return void
     */
    private function applyOverrides(array $overrides)
    {
        foreach ($overrides as $configKey => $value) {
            Config::set($configKey, $value);
        }
        //Log::debug('Applied ' . count($overrides) . ' configuration overrides from cache');
    }
    
    /**
     * Clears the configuration cache
     *
     * @return void
     */
    public static function clearConfigCache()
    {
        Cache::forget(self::CONFIG_CACHE_KEY);
    }

    /**
     * Checks if the required database tables exist
     *
     * @return bool True if all required tables exist, false otherwise
     */
    private function requiredTablesExist(): bool
    {
        try {
            // Check if app_settings table exists
            DB::select("SELECT 1 FROM app_settings LIMIT 1");
            
            // Check if cache table exists (if using database cache driver)
            if (config('cache.default') === 'database') {
                DB::select("SELECT 1 FROM cache LIMIT 1");
            }
            
            return true;
        } catch (\Exception $e) {
            // Tables don't exist or database connection failed
            return false;
        }
    }
}

