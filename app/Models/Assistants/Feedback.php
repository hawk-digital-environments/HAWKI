<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('feedback')]
class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'assistant_id',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
