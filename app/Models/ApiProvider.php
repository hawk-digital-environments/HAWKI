<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;

class ApiProvider extends Model
{
    use AsSource, Filterable, HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Clear caches when provider is saved or deleted
        static::saved(function ($provider) {
            $provider->clearUrlCaches();
            static::clearAiConfigCache();
        });

        static::deleted(function ($provider) {
            $provider->clearUrlCaches();
            static::clearAiConfigCache();
        });
    }

    /**
     * Clear all AI configuration caches
     */
    protected static function clearAiConfigCache(): void
    {
        Cache::forget('ai_config_default_models');
        Cache::forget('ai_config_system_models');
        Cache::forget('ai_config_providers');
        
        // Clear tagged cache if driver supports it
        try {
            Cache::tags(['ai_config'])->flush();
        } catch (\BadMethodCallException $e) {
            // Cache driver doesn't support tags, skip
        }
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

    protected $table = 'api_providers';

    protected $fillable = [
        'provider_name',
        'api_format_id',
        'api_key',
        'base_url',
        'is_active',
        'display_order',
        'additional_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_settings' => 'array',
        'api_key' => 'encrypted',
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
        'display_order' => Where::class,
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
        'display_order',
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
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($builder, $value)
    {
        return $builder->where(function ($query) use ($value) {
            $query->where('provider_name', 'like', "%{$value}%")
                ->orWhere('base_url', 'like', "%{$value}%")
                ->orWhereHas('apiFormat', function ($subQuery) use ($value) {
                    $subQuery->where('unique_name', 'like', "%{$value}%")
                        ->orWhere('display_name', 'like', "%{$value}%");
                });
        });
    }

    /**
     * Scope to order providers by display_order, then by name
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($builder)
    {
        return $builder->orderBy('display_order')->orderBy('provider_name');
    }

    /**
     * Relationship with AI models
     */
    public function aiModels()
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * Legacy relationship - use aiModels() instead
     *
     * @deprecated Use aiModels() method instead
     */
    public function languageModels()
    {
        return $this->aiModels();
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
     * Get the base URL from provider's own base_url field
     */
    public function getBaseUrlAttribute(): ?string
    {
        return $this->attributes['base_url'] ?? null;
    }

    /**
     * Find provider by name (convenience method)
     */
    public static function findByName(string $providerName): ?self
    {
        return static::where('provider_name', $providerName)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active providers with their endpoint URLs
     */
    public static function getAllWithEndpoints(): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['apiFormat.activeEndpoints'])
            ->where('is_active', true)
            ->get()
            ->each(function ($provider) {
                $provider->setRelation('endpoint_urls_collection', collect($provider->getAllEndpointUrls()));
                $provider->setAttribute('endpoint_urls', $provider->getAllEndpointUrls());
            });
    }

    /**
     * Find providers that support a specific endpoint
     */
    public static function findByEndpoint(string $endpointName): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereHas('apiFormat.endpoints', function ($query) use ($endpointName) {
            $query->where('name', $endpointName)->where('is_active', true);
        })->where('is_active', true)->get();
    }

    /**
     * Generic method to get URL for any endpoint type with caching
     */
    public function getUrlForEndpoint(string $endpointName): ?string
    {
        $cacheKey = "provider_{$endpointName}_url_{$this->id}_{$this->updated_at?->timestamp}";

        return Cache::remember($cacheKey, self::URL_CACHE_TTL, function () use ($endpointName) {
            $endpoint = $this->apiFormat?->getEndpoint($endpointName);

            return $endpoint?->getFullUrlForProvider($this);
        });
    }

    /**
     * Get the models list endpoint URL
     */
    public function getModelsUrl(): ?string
    {
        return $this->getUrlForEndpoint('models.list');
    }

    /**
     * Get the chat endpoint URL with caching
     */
    public function getChatUrlAttribute(): ?string
    {
        $cacheKey = "provider_chat_url_{$this->id}_{$this->updated_at?->timestamp}";

        return Cache::remember($cacheKey, self::URL_CACHE_TTL, function () {
            $chatEndpoint = $this->apiFormat?->getChatEndpoint();

            return $chatEndpoint?->getFullUrlForProvider($this);
        });
    }

    /**
     * Get the chat completions endpoint URL (convenience method)
     */
    public function getChatUrl(): ?string
    {
        // Use attribute accessor for backward compatibility
        return $this->chat_url;
    }

    /**
     * Get the completions endpoint URL (for legacy OpenAI API)
     */
    public function getCompletionsUrl(): ?string
    {
        return $this->getUrlForEndpoint('completions.create');
    }

    /**
     * Get the embeddings endpoint URL
     */
    public function getEmbeddingsUrl(): ?string
    {
        return $this->getUrlForEndpoint('embeddings.create');
    }

    /**
     * Get all available endpoint URLs for this provider
     */
    public function getAllEndpointUrls(): array
    {
        $urls = [];

        if ($this->apiFormat) {
            foreach ($this->apiFormat->activeEndpoints as $endpoint) {
                $urls[$endpoint->name] = $endpoint->getFullUrlForProvider($this);
            }
        }

        return $urls;
    }

    /**
     * Check if a specific endpoint is available for this provider
     */
    public function hasEndpoint(string $endpointName): bool
    {
        return $this->apiFormat?->getEndpoint($endpointName) !== null;
    }

    /**
     * Validate if the base URL is accessible (basic connectivity check)
     */
    public function isBaseUrlAccessible(int $timeout = 5): bool
    {
        if (! $this->base_url) {
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout($timeout)->get($this->base_url);

            return $response->status() < 500; // Accept any non-server error
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get health status of this provider
     */
    public function getHealthStatus(): array
    {
        return [
            'provider_name' => $this->provider_name,
            'is_active' => $this->is_active,
            'has_base_url' => ! empty($this->base_url),
            'has_api_key' => ! empty($this->api_key),
            'base_url_accessible' => $this->isBaseUrlAccessible(),
            'available_endpoints' => array_keys($this->getAllEndpointUrls()),
            'total_endpoints' => $this->apiFormat?->activeEndpoints->count() ?? 0,
        ];
    }

    /**
     * Clear URL caches for this provider
     */
    public function clearUrlCaches(): void
    {
        // Clear all endpoint-specific caches
        if ($this->apiFormat) {
            foreach ($this->apiFormat->endpoints as $endpoint) {
                Cache::forget("provider_{$endpoint->name}_url_{$this->id}_{$this->updated_at?->timestamp}");
            }
        }

        // Clear legacy chat URL cache
        Cache::forget("provider_chat_url_{$this->id}_{$this->updated_at?->timestamp}");

        // Also clear related endpoint caches
        if ($this->apiFormat) {
            foreach ($this->apiFormat->endpoints as $endpoint) {
                $endpoint->clearUrlCache();
            }
        }
    }
}
