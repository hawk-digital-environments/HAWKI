<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('reviews')]
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_id',
        'status',
        'reason',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
