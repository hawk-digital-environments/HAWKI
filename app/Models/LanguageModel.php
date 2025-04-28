<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class LanguageModel extends Model
{
    use AsSource;

    protected $fillable = [
        'model_id',
        'label',
        'provider_id',
        'is_active',
        'streamable',
        'is_visible',
        'display_order',
        'information',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'streamable' => 'boolean',
        'is_visible' => 'boolean',
        'display_order' => 'integer',
        'information' => 'array',
        'settings' => 'array',
    ];

    /**
     * Get the provider that owns the model.
     */
    public function provider()
    {
        return $this->belongsTo(ProviderSetting::class, 'provider_id');
    }
}
