<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Table('tags')]
class Tag extends Model
{
    protected $fillable = [
        'text',
    ];

    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(Assistant::class, 'assistant_tag');
    }
}
