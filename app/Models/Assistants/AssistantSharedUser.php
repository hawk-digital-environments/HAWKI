<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the assistant_shared_users pivot: an exclusive-audience member
 * (`can_edit` false) or a co-author (`can_edit` true). Exists so share checks
 * can eager-load the pivot directly (see AssistantSchema::with()), without
 * the User model's access scope filtering stranger sharees out.
 */
class AssistantSharedUser extends Model
{
    protected $table = 'assistant_shared_users';

    protected $fillable = [
        'assistant_id',
        'user_id',
        'can_edit',
    ];

    protected function casts(): array
    {
        return [
            'can_edit' => 'boolean',
        ];
    }
}
