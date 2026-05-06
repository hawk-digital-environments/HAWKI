<?php

declare(strict_types=1);

namespace App\Http\Resources\UserPrompt;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserPromptResource extends JsonApiResource
{
    public $attributes = [
        'text',
        'created_at',
        'updated_at',
    ];
}
