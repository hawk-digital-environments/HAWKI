<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Models\User;
use App\Policies\AssistantReviewPolicy;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Assistant                       $assistant
 * @property int                             $assistant_id
 * @property int                             $id
 * @property null|string                     $reason
 * @property null|\Illuminate\Support\Carbon $reviewed_at
 * @property null|User                       $reviewer
 * @property null|int                        $reviewer_id
 * @property AssistantReviewStatus           $status
 */
#[Table('assistant_reviews')]
#[UsePolicy(AssistantReviewPolicy::class)]
class AssistantReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssistantReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
