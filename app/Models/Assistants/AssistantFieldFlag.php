<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Models\User;
use App\Policies\AssistantFieldFlagPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's flag+comment on a specific field of an assistant (optionally
 * anchored to a selected excerpt of that field's text), shown to the creator
 * as review feedback.
 *
 * @property Assistant                       $assistant
 * @property int                             $assistant_id
 * @property null|User                       $admin
 * @property null|int                        $admin_user_id
 * @property string                          $comment
 * @property null|\Illuminate\Support\Carbon $created_at
 * @property null|string                     $excerpt
 * @property string                          $field
 * @property int                             $id
 * @property bool                            $resolved
 * @property null|\Illuminate\Support\Carbon $updated_at
 */
#[Table('assistant_field_flags')]
#[UsePolicy(AssistantFieldFlagPolicy::class)]
class AssistantFieldFlag extends Model
{
    protected $fillable = [
        'field',
        'excerpt',
        'comment',
        'resolved',
    ];

    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
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
