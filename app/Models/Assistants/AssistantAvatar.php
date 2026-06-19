<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistantAvatar extends Model
{
    use HasFactory;

    public const STORAGE_CATEGORY = 'assistant_avatars';

    protected $fillable = [
        'uuid',
        'name',
    ];
}
