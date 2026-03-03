<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
    protected $fillable = [
        'name',
        'active',
        'api_key',
        'api_url',
        'ping_url',
    ];
    protected $hidden = [
        'api_key',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

}
