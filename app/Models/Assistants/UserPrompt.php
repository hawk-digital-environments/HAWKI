<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;

#[Table('user_prompts')]
class UserPrompt extends Model
{
    protected $fillable = [
        'text'
    ];
}
