<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Models\User;
use App\Policies\AssistantFeedbackPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Assistant $assistant
 * @property int       $assistant_id
 * @property int       $id
 * @property string    $text
 * @property User      $user
 * @property int       $user_id
 */
#[Table('assistant_feedback')]
#[UsePolicy(AssistantFeedbackPolicy::class)]
class AssistantFeedback extends Model
{
    use HasFactory;
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
