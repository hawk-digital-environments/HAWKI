<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('versions')]
class Version extends Model
{
    protected $fillable = [
        'assistant_id',
        'text',
        'version',
    ];

    protected $casts = [
        'version' => 'decimal:1',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
