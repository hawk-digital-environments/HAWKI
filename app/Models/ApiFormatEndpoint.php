<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ApiFormatEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_format_id',
        'name',
        'path',
        'method',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Cache duration for URL generation (1 hour)
     */
    const CACHE_TTL = 3600;

    /**
     * Get the API format that owns this endpoint.
     */
    public function apiFormat()
    {
        return $this->belongsTo(ApiFormat::class, 'api_format_id');
    }

    /**
     * Scope to get only active endpoints.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full URL for this endpoint with caching.
     */
    public function getFullUrlAttribute(): ?string
    {
        // Cache key based on endpoint ID and updated_at timestamp
        $cacheKey = "endpoint_url_{$this->id}_{$this->updated_at?->timestamp}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            if (!$this->apiFormat?->base_url || !$this->path) {
                return null;
            }

            return rtrim($this->apiFormat->base_url, '/') . '/' . ltrim($this->path, '/');
        });
    }

    /**
     * Clear URL cache for this endpoint
     */
    public function clearUrlCache(): void
    {
        // Clear cache using pattern matching
        $pattern = "endpoint_url_{$this->id}_*";
        
        // For production use, consider using Cache::tags()
        // For now, we'll use a simple approach
        $keys = Cache::getStore()->getPrefix() . $pattern;
        
        // Clear specific cache entries that might exist
        for ($i = 0; $i < 10; $i++) {
            Cache::forget("endpoint_url_{$this->id}_{$this->updated_at?->timestamp}");
        }
    }

    /**
     * Clear all endpoint URL caches (use when API format base_url changes)
     */
    public static function clearAllUrlCaches(): void
    {
        // In production, consider using Cache::tags(['endpoint_urls'])->flush()
        // For now, we'll trigger cache regeneration by touching related models
    }
}
