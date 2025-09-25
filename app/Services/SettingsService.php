<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private $cachePrefix = 'app_settings_';

    private $cacheTtl = 3600; // 1 hour

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
        // Convert config key format to database key format if needed
        // auth.local_authentication -> auth_local_authentication
        $pos = strpos($key, '.');
        if ($pos !== false) {
            // Nur den ERSTEN Punkt durch einen Unterstrich ersetzen
            $configFile = substr($key, 0, $pos);
            $realKey = substr($key, $pos + 1);
            $dbKey = $configFile.'_'.$realKey;
        } else {
            $dbKey = $key;
        }

        $value = Cache::remember("settings.$dbKey", 3600, function () use ($dbKey, $default) {
            $setting = AppSetting::where('key', $dbKey)->first();

            return $setting ? $setting->typed_value : $default;
        });

        // Apply special transformations for specific config keys
        return $this->transformValueForConfig($key, $value);
    }

    /**
     * Set a setting value
     */
    public function set(string $key, $value, string $type = 'string'): void
    {
        // Convert config key format to database key format if needed
        $pos = strpos($key, '.');
        if ($pos !== false) {
            // Nur den ERSTEN Punkt durch einen Unterstrich ersetzen
            $configFile = substr($key, 0, $pos);
            $realKey = substr($key, $pos + 1);
            $dbKey = $configFile.'_'.$realKey;
        } else {
            $dbKey = $key;
        }

        $setting = AppSetting::updateOrCreate(
            ['key' => $dbKey],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type,
            ]
        );

        // Clear cache
        Cache::forget("settings.$dbKey");

        // Also set in current config for immediate use
        $configKey = $this->convertDbKeyToConfigKey($dbKey);
        config([$configKey => $this->convertValue($value, $type)]);
    }

    /**
     * Get all settings formatted for Laravel config system.
     * Returns an associative array with config keys (dot notation) and typed values.
     */
    public function getAllForConfig()
    {
        return AppSetting::all()->mapWithKeys(function ($setting) {
            // Convert database key to config key: nur der ERSTE Unterstrich wird zu einem Punkt
            // auth_local_authentication -> auth.local_authentication (korrekt!)
            // nicht: auth.local.authentication (falsch!)
            $pos = strpos($setting->key, '_');
            if ($pos !== false) {
                $configFile = substr($setting->key, 0, $pos);
                $realKey = substr($setting->key, $pos + 1);
                $configKey = $configFile.'.'.$realKey;
            } else {
                $configKey = $setting->key;
            }

            // Apply special transformations for specific config keys
            $value = $this->transformValueForConfig($configKey, $setting->typed_value);

            return [$configKey => $value];
        })->toArray();
    }

    /**
     * Apply special transformations for specific configuration keys
     */
    private function transformValueForConfig(string $configKey, $value)
    {
        return match ($configKey) {
            'hawki.aiHandle' => '@'.ltrim($value, '@'), // Always add @ prefix, remove existing @ first
            default => $value,
        };
    }

    /**
     * Convert value to proper type
     */
    private function convertValue($value, string $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    /**
     * Convert database key to config key: nur der ERSTE Unterstrich wird zu einem Punkt
     */
    private function convertDbKeyToConfigKey(string $dbKey): string
    {
        $pos = strpos($dbKey, '_');
        if ($pos !== false) {
            $configFile = substr($dbKey, 0, $pos);
            $realKey = substr($dbKey, $pos + 1);

            return $configFile.'.'.$realKey;
        }

        return $dbKey;
    }
}
