<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Assistant                       $assistant
 * @property int                             $assistant_id
 * @property null|array<int, string>         $changed_keys
 * @property null|\Illuminate\Support\Carbon $created_at
 * @property int                             $id
 * @property null|array<string, mixed>       $payload
 * @property string                          $text
 * @property null|\Illuminate\Support\Carbon $updated_at
 * @property string                          $version
 */
#[Table('assistant_versions')]
#[UsePolicy(AssistantVersionPolicy::class)]
class AssistantVersion extends Model
{
    /**
     * The version number of the mutable working-draft row every assistant
     * carries. Content edits accumulate on it; approving a review promotes it
     * to the next published version number and a fresh draft row is respawned
     * in its place.
     *
     * @see \App\Services\Assistant\AssistantVersionService
     */
    public const float DRAFT_VERSION = 0.0;

    protected $fillable = [
        'text',
        'changed_keys',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    protected function casts(): array
    {
        return [
            'version' => 'decimal:1',
            'changed_keys' => 'array',
            'payload' => 'array',
        ];
    }
}
