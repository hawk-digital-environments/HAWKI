<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantUserPromptPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Assistant $assistant
 * @property int       $assistant_id
 * @property int       $id
 * @property string    $text
 */
#[Table('assistant_user_prompts')]
#[UsePolicy(AssistantUserPromptPolicy::class)]
class AssistantUserPrompt extends Model
{
    protected $fillable = [
        'text',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
