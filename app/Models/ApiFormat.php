<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Screen\AsSource;

class ApiFormat extends Model
{
    use AsSource, Filterable, HasFactory;

    protected $fillable = [
        'unique_name',
        'display_name',
        'metadata',
        'provider_class',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'unique_name' => Like::class,
        'display_name' => Like::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'id',
        'unique_name',
        'display_name',
        'created_at',
        'updated_at',
        'endpoints_count',
        'api_providers_count',
    ];

    /**
     * Get the providers that use this API format.
     */
    public function providers()
    {
        return $this->hasMany(ApiProvider::class, 'api_format_id');
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use providers() instead
     */
    public function providerSettings()
    {
        return $this->providers();
    }

    /**
     * Get the endpoints for this API format.
     */
    public function endpoints()
    {
        return $this->hasMany(ApiFormatEndpoint::class, 'api_format_id');
    }

    /**
     * Get only active endpoints for this API format.
     */
    public function activeEndpoints()
    {
        return $this->hasMany(ApiFormatEndpoint::class, 'api_format_id')->where('is_active', true);
    }

    /**
     * Scope to get only active API formats (removed is_active field from schema).
     */
    public function scopeActive($query)
    {
        return $query;
    }

    /**
     * Custom sorting scope for endpoints_count
     */
    public function scopeSortByEndpointsCount($query, $direction = 'asc')
    {
        return $query->withCount('endpoints')->orderBy('endpoints_count', $direction);
    }

    /**
     * Custom sorting scope for api_providers_count
     */
    public function scopeSortByApiProvidersCount($query, $direction = 'asc')
    {
        return $query->withCount('providers')->orderBy('api_providers_count', $direction);
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
            $query->where('unique_name', 'like', "%{$value}%")
                ->orWhere('display_name', 'like', "%{$value}%")
                ->orWhereJsonContains('metadata', $value);
        });
    }

    /**
     * Custom usage filter
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUsage($builder, $value)
    {
        switch ($value) {
            case 'used':
                return $builder->whereHas('providers');
            case 'unused':
                return $builder->whereDoesntHave('providers');
            default:
                return $builder;
        }
    }

    /**
     * Custom features filter
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  string  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeatures($builder, $value)
    {
        switch ($value) {
            case 'streaming':
                return $builder->whereJsonContains('metadata->supports_streaming', true);
            case 'functions':
                return $builder->whereJsonContains('metadata->supports_function_calling', true);
            case 'grounding':
                return $builder->whereJsonContains('metadata->supports_grounding', true);
            case 'vision':
                return $builder->whereJsonContains('metadata->supports_vision', true);
            default:
                return $builder;
        }
    }

    /**
     * Get a specific endpoint by name.
     */
    public function getEndpoint(string $name): ?ApiFormatEndpoint
    {
        return $this->endpoints()->where('name', $name)->first();
    }

    /**
     * Get the models endpoint for this API format.
     */
    public function getModelsEndpoint(): ?ApiFormatEndpoint
    {
        return $this->getEndpoint('models.list');
    }

    /**
     * Get the chat completions endpoint for this API format.
     */
    public function getChatEndpoint(): ?ApiFormatEndpoint
    {
        // Handle different endpoint names for different API formats
        $chatEndpointNames = [
            'chat.create',      // Standard OpenAI API
            'responses.create', // OpenAI Responses API
            'completions.create', // Legacy completions
        ];

        foreach ($chatEndpointNames as $endpointName) {
            $endpoint = $this->getEndpoint($endpointName);
            if ($endpoint) {
                return $endpoint;
            }
        }

        return null;
    }

    /**
     * Clear all related caches when API format changes
     */
    public function clearRelatedCaches(): void
    {
        // Clear endpoint URL caches
        foreach ($this->endpoints as $endpoint) {
            $endpoint->clearUrlCache();
        }

        // Clear provider URL caches
        foreach ($this->providers as $provider) {
            $provider->clearUrlCaches();
        }

        // Clear any cached provider instances in factory
        Cache::forget('provider_instances_cleared_'.time());
    }
}
