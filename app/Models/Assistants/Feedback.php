<?php

namespace App\Models\Assistants;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Table('feedback')]
class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'assistant_id',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Feedback $feedback): void {
            $feedback->user_id ??= Auth::id();
        });
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
