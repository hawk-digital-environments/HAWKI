<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_name',
        'display_name',
        'base_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the providers that use this API format.
     */
    public function providers()
    {
        return $this->hasMany(ProviderSetting::class, 'api_format_id');
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
        return $this->getEndpoint('chat.create');
    }
}
