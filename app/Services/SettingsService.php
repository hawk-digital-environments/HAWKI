<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private $cachePrefix = 'app_settings_';
    private $cacheTtl = 3600; // 1 hour

    /**
     * Get a setting value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return Cache::remember($this->cachePrefix . $key, $this->cacheTtl, function () use ($key, $default) {
            $setting = AppSetting::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }
            
            return $setting->typed_value;
        });
    }

    /**
     * Set a setting value.
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $group
     * @param string|null $type
     * @param string|null $description
     * @return AppSetting
     */
    public function set($key, $value, $group = null, $type = null, $description = null)
    {
        $setting = AppSetting::where('key', $key)->first();
        
        if (!$setting) {
            $setting = new AppSetting([
                'key' => $key,
                'group' => $group ?? 'basic',
                'type' => $type ?? $this->guessType($value),
                'description' => $description,
            ]);
        }
        
        $setting->value = $value;
        $setting->save();
        
        // Clear the cache for this setting
        Cache::forget($this->cachePrefix . $key);
        
        return $setting;
    }

    /**
     * Get all settings by group.
     *
     * @param string|null $group
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllByGroup($group = null)
    {
        $query = AppSetting::query();
        
        if ($group) {
            $query->where('group', $group);
        }
        
        return $query->get();
    }

    /**
     * Get all settings as key-value pairs.
     *
     * @param bool $includePrivate
     * @return array
     */
    public function getAllAsArray($includePrivate = false)
    {
        $query = AppSetting::query();
        
        if (!$includePrivate) {
            $query->where('is_private', false);
        }
        
        return $query->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->typed_value];
        })->toArray();
    }

    /**
     * Guess the type of a value.
     *
     * @param mixed $value
     * @return string
     */
    private function guessType($value)
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value)) {
            return 'integer';
        }
        
        if (is_array($value)) {
            return 'json';
        }
        
        return 'string';
    }
}
