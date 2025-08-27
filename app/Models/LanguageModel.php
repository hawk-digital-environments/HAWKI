<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Screen\AsSource;
use Orchid\Filters\Filterable;

class LanguageModel extends Model
{
    use AsSource, Filterable;

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
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'model_id' => Like::class,
        'label' => Like::class,
        'provider_id' => Where::class,
        'is_active' => Where::class,
        'is_visible' => Where::class,
        'streamable' => Where::class,
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
        'model_id',
        'label',
        'provider_id',
        'is_active',
        'is_visible',
        'streamable',
        'display_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the provider that owns the model.
     */
    public function provider()
    {
        return $this->belongsTo(ProviderSetting::class, 'provider_id');
    }
}
