<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Get the full URL for this endpoint.
     */
    public function getFullUrlAttribute(): ?string
    {
        if (!$this->apiFormat?->base_url || !$this->path) {
            return null;
        }

        return rtrim($this->apiFormat->base_url, '/') . '/' . ltrim($this->path, '/');
    }
}
