<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class AppSetting extends Model
{
    use AsSource;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'is_private',
    ];

    /**
     * Get the properly typed value of the setting.
     *
     * @return mixed
     */
    public function getTypedValueAttribute()
    {
        switch ($this->type) {
            case 'boolean':
                return (bool) $this->value;
            case 'integer':
                return (int) $this->value;
            case 'json':
                return json_decode($this->value, true);
            case 'string':
            default:
                return $this->value;
        }
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
