<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Models\User;
use App\Policies\AssistantReviewLogPolicy;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per review decision (approve/deny/block) — an append-only history,
 * unlike `assistant_reviews` which only keeps the current state.
 *
 * @property AssistantReviewStatus           $action
 * @property Assistant                       $assistant
 * @property int                             $assistant_id
 * @property null|User                       $admin
 * @property null|int                        $admin_user_id
 * @property null|\Illuminate\Support\Carbon $created_at
 * @property int                             $id
 * @property null|string                     $reason
 */
#[Table('assistant_review_logs')]
#[UsePolicy(AssistantReviewLogPolicy::class)]
class AssistantReviewLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_user_id',
        'action',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'action' => AssistantReviewStatus::class,
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
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
