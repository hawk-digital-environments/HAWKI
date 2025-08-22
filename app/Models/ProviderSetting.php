<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;
use Orchid\Filters\Filterable;

class ProviderSetting extends Model
{
    use AsSource, Filterable;

    protected $fillable = [
        'provider_name',
        'api_format',
        'api_key',
        'base_url',
        'ping_url',
        'is_active',
        'additional_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_settings' => 'array',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'provider_name' => Like::class,
        'api_format' => Where::class,
        'base_url' => Like::class,
        'ping_url' => Like::class,
        'is_active' => Where::class,
        'search' => Like::class, // Custom search filter
        'created_at' => WhereDateStartEnd::class,
        'updated_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'id',
        'provider_name',
        'api_format',
        'base_url',
        'is_active',
        'created_at',
        'updated_at',
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

    /**
     * Custom search filter across multiple fields
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($builder, $value)
    {
        return $builder->where(function ($query) use ($value) {
            $query->where('provider_name', 'like', "%{$value}%")
                  ->orWhere('base_url', 'like', "%{$value}%")
                  ->orWhere('ping_url', 'like', "%{$value}%")
                  ->orWhere('api_format', 'like', "%{$value}%");
        });
    }

    /**
     * Relationship with language models
     */
    public function languageModels()
    {
        return $this->hasMany(LanguageModel::class, 'provider_id');
    }
}
