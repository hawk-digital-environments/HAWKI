<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Assistant   $assistant
 * @property int         $assistant_id
 * @property int         $id
 * @property null|string $reason
 * @property string      $status
 */
#[Table('assistant_reviews')]
#[UsePolicy(AssistantReviewPolicy::class)]
class AssistantReview extends Model
{
    use HasFactory;
    protected $fillable = [
        'assistant_id',
        'status',
        'reason',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
