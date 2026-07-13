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
 * @property string                          $text
 * @property null|\Illuminate\Support\Carbon $updated_at
 * @property string                          $version
 */
#[Table('assistant_versions')]
#[UsePolicy(AssistantVersionPolicy::class)]
class AssistantVersion extends Model
{
    protected $fillable = [
        'assistant_id',
        'text',
        'version',
        'changed_keys',
    ];
    protected $casts = [
        'version' => 'decimal:1',
        'changed_keys' => 'array',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
