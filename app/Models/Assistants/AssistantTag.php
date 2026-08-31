<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantTagPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int    $id
 * @property string $text
 */
#[Table('assistant_tags')]
#[UsePolicy(AssistantTagPolicy::class)]
class AssistantTag extends Model
{
    protected $fillable = [
        'text',
    ];

    /**
     * @return BelongsToMany<Assistant, $this>
     */
    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(Assistant::class, 'assistant_tag', 'tag_id', 'assistant_id');
    }
}
