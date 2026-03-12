<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
    protected $fillable = [
        'provider_id',  // config key, e.g. 'openAi', 'gwdg'
        'name',
        'active',
        'api_url',
        'ping_url',
    ];

    protected $hidden = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }
}
