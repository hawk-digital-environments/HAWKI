<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantAvatarPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property null|Assistant $assistant
 * @property null|int       $assistant_id
 * @property string         $icon_css
 * @property int            $id
 * @property string         $name
 */
#[UsePolicy(AssistantAvatarPolicy::class)]
class AssistantAvatar extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'icon_css',
        'assistant_id',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
