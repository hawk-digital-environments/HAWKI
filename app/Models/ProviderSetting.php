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
        'api_format_id',
        'api_key',
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
        'api_format_id' => Where::class,
        'is_active' => Where::class,
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
        'api_format_id',
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
                  ->orWhereHas('apiFormat', function ($subQuery) use ($value) {
                      $subQuery->where('unique_name', 'like', "%{$value}%")
                               ->orWhere('display_name', 'like', "%{$value}%")
                               ->orWhere('base_url', 'like', "%{$value}%");
                  });
        });
    }

    /**
     * Relationship with language models
     */
    public function languageModels()
    {
        return $this->hasMany(LanguageModel::class, 'provider_id');
    }

    /**
     * Relationship with API format
     */
    public function apiFormat()
    {
        return $this->belongsTo(ApiFormat::class, 'api_format_id');
    }

    /**
     * Get the API format name (backward compatibility)
     */
    public function getApiFormatNameAttribute(): string
    {
        return $this->apiFormat?->unique_name ?? 'openai-api';
    }

    /**
     * Get the base URL from API format
     */
    public function getBaseUrlAttribute(): ?string
    {
        return $this->apiFormat?->base_url;
    }

    /**
     * Get the models endpoint URL (ping_url equivalent)
     */
    public function getPingUrlAttribute(): ?string
    {
        $modelsEndpoint = $this->apiFormat?->getModelsEndpoint();
        return $modelsEndpoint?->full_url;
    }

    /**
     * Get the chat endpoint URL
     */
    public function getChatUrlAttribute(): ?string
    {
        $chatEndpoint = $this->apiFormat?->getChatEndpoint();
        return $chatEndpoint?->full_url;
    }
}
