<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A share link: a multi-use, expiring token that adds whoever redeems it to
 * the assistant's share list with the link's `can_edit` grant.
 *
 * @property int    $id
 * @property int    $assistant_id
 * @property ?int   $created_by
 * @property string $token_hash
 * @property bool   $can_edit
 * @property Carbon $expires_at
 */
class AssistantShareLink extends Model
{
    use Prunable;

    protected $fillable = [
        'assistant_id',
        'created_by',
        'token_hash',
        'can_edit',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'can_edit' => 'boolean',
            'expires_at' => 'datetime',
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
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
