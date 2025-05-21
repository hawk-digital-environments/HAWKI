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
     * Cache-Schlüssel für die Konfigurationsüberschreibungen
     */
    const CONFIG_CACHE_KEY = 'app_settings_overrides';
    
    /**
     * Zeit in Sekunden, wie lange die Konfigurationseinstellungen gecached werden sollen
     */
    const CACHE_TTL = 3600; // 1 Stunde
    
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
            // Versuche zuerst, die überschriebenen Konfigurationen aus dem Cache zu laden
            $cachedOverrides = $this->getCachedOverrides();
            
            if ($cachedOverrides !== null) {
                // Cache-Hit: Verwende die gecachten Überschreibungen
                //Log::debug('Using cached configuration overrides');
                $this->applyOverrides($cachedOverrides);
                return;
            }
            
            // Cache-Miss: Lade die Konfigurationseinstellungen aus settings.php
            $configSettings = config('settings');
            
            if (empty($configSettings)) {
                Log::info('No config settings defined in settings.php. Skipping config override from database.');
                return;
            }
            
            // Erzeuge eine flache Liste aller überschreibbaren Keys
            $overridableKeys = [];
            $dbKeysToLoad = [];
            
            foreach ($configSettings as $configFile => $keys) {
                // Überspringe das Gruppen-Mapping
                if ($configFile === 'group_mapping') {
                    continue;
                }
                
                // Prüfe, ob der Eintrag ein Array ist (ein valider Key)
                if (!is_array($keys)) continue;
                
                foreach ($keys as $key => $value) {
                    // Wenn der Schlüssel numerisch ist, verwenden wir den Wert als Schlüssel und keine Beschreibung
                    if (is_int($key)) {
                        $realKey = $value;
                    } else {
                        // Ansonsten ist der Key der Schlüssel und der Wert die Beschreibung
                        $realKey = $key;
                    }
                    
                    // DB-Key erstellen (mit Unterstrich): app_name
                    $dbKey = "{$configFile}_{$realKey}";
                    // Config-Key erstellen (mit Punkt): app.name
                    $configKey = "{$configFile}.{$realKey}";
                    
                    $overridableKeys[$dbKey] = $configKey;
                    $dbKeysToLoad[] = $dbKey;
                }
            }
            
            if (empty($dbKeysToLoad)) {
                Log::info('No overridable keys found in settings.php. Skipping database load.');
                return;
            }

            Log::debug("Loading settings from database for keys: " . implode(', ', $dbKeysToLoad));
            
            // Lade alle relevanten Einstellungen aus der Datenbank
            $settings = DB::table('app_settings')
                ->whereIn('key', $dbKeysToLoad)
                ->get();
            
            if ($settings->isEmpty()) {
                Log::info('No matching settings found in database for defined keys.');
                return;
            }
            
            // Erstelle ein Array mit den Überschreibungen für den Cache
            $overrides = [];
            
            foreach ($settings as $setting) {
                // Prüfen, ob der Schlüssel in der Liste der überschreibbaren Schlüssel ist
                if (isset($overridableKeys[$setting->key])) {
                    // Den entsprechenden Config-Schlüssel abrufen
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
                    
                    // Speichere die Überschreibung für den Cache
                    $overrides[$configKey] = $value;
                    
                    // Überschreibe den Konfigurationswert direkt
                    Config::set($configKey, $value);
                    //Log::debug("Overriding config key {$configKey} with value from database");
                }
            }
            
            // Cache die Überschreibungen für zukünftige Anfragen
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
     * Holt die gecachten Konfigurationsüberschreibungen
     *
     * @return array|null Die gecachten Überschreibungen oder null, wenn keine im Cache
     */
    private function getCachedOverrides()
    {
        return Cache::get(self::CONFIG_CACHE_KEY);
    }
    
    /**
     * Speichert die Konfigurationsüberschreibungen im Cache
     *
     * @param array $overrides Die zu cachenden Überschreibungen
     * @return void
     */
    private function cacheOverrides(array $overrides)
    {
        Cache::put(self::CONFIG_CACHE_KEY, $overrides, self::CACHE_TTL);
        Log::debug('Cached ' . count($overrides) . ' configuration overrides for ' . self::CACHE_TTL . ' seconds');
    }
    
    /**
     * Wendet die gecachten Überschreibungen auf die Konfiguration an
     *
     * @param array $overrides Die anzuwendenden Überschreibungen
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
     * Löscht den Konfigurationscache
     *
     * @return void
     */
    public static function clearConfigCache()
    {
        Cache::forget(self::CONFIG_CACHE_KEY);
        Log::debug('Configuration override cache cleared');
    }
}
