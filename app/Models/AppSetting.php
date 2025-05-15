<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class AppSetting extends Model
{
    use AsSource;

    protected $fillable = [
        'key',              // setting key mit Unterstrichen (app_name)
        'value',            // value
        'source',           // config source file without .php (e.g. app / sanctum / session)
        'group',            // grouping for ui display
        'type',             // data type
        'description',      // what is this setting for?
        'is_private',       // table entry can be modied via ui
    ];

    /**
     * Get the properly typed value of the setting.
     *
     * @return mixed
     */
    public function getTypedValueAttribute()
    {
        $value = $this->getRawValue();
        
        switch ($this->type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'json':
                return is_array($value) ? $value : json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Get the config key in dot notation.
     *
     * @return string
     */
    public function getConfigKeyAttribute(): string
    {
        return str_replace('_', '.', $this->key);
    }

    /**
     * Get the raw value, with fallback to environment variable if needed
     *
     * @return mixed
     */
    public function getRawValue()
    {
        // If value exists in database, use it
        if (!is_null($this->value)) {
            return $this->value;
        }
        
        // Otherwise try to get from .env via source if defined
        if (!empty($this->source)) {
            return env($this->source);
        }
        
        return $this->value;
    }

    /**
     * Set the value with proper type conversion.
     *
     * @param mixed $value
     * @return void
     */
    public function setValueAttribute($value)
    {
        if ($this->type === 'json' && is_array($value)) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }
}
