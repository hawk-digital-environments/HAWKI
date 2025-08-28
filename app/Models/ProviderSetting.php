<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
     * Cache duration for URL generation (1 hour)
     */
    const URL_CACHE_TTL = 3600;

    /**
     * Get the base URL from API format
     */
    public function getBaseUrlAttribute(): ?string
    {
        return $this->apiFormat?->base_url;
    }

    /**
     * Get the models endpoint URL (ping_url equivalent) with caching
     */
    public function getPingUrlAttribute(): ?string
    {
        $cacheKey = "provider_ping_url_{$this->id}_{$this->updated_at?->timestamp}";
        
        return Cache::remember($cacheKey, self::URL_CACHE_TTL, function () {
            $modelsEndpoint = $this->apiFormat?->getModelsEndpoint();
            return $modelsEndpoint?->full_url;
        });
    }

    /**
     * Get the chat endpoint URL with caching
     */
    public function getChatUrlAttribute(): ?string
    {
        $cacheKey = "provider_chat_url_{$this->id}_{$this->updated_at?->timestamp}";
        
        return Cache::remember($cacheKey, self::URL_CACHE_TTL, function () {
            $chatEndpoint = $this->apiFormat?->getChatEndpoint();
            return $chatEndpoint?->full_url;
        });
    }

    /**
     * Clear URL caches for this provider
     */
    public function clearUrlCaches(): void
    {
        Cache::forget("provider_ping_url_{$this->id}_{$this->updated_at?->timestamp}");
        Cache::forget("provider_chat_url_{$this->id}_{$this->updated_at?->timestamp}");
        
        // Also clear related endpoint caches
        if ($this->apiFormat) {
            foreach ($this->apiFormat->endpoints as $endpoint) {
                $endpoint->clearUrlCache();
            }
        }
    }
}
