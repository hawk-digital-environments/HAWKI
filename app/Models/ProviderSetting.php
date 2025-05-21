<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class ProviderSetting extends Model
{
    use AsSource;

    protected $fillable = [
        'provider_name',
        'api_format',
        'api_key',
        'base_url',
        'ping_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'id';
    }
}
