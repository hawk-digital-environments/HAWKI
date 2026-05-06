<?php

declare(strict_types=1);

namespace App\Http\Resources\Language;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class LanguageResource extends JsonApiResource
{
    public $attributes = [
        'text',
        'created_at',
        'updated_at',
    ];

    public $relationships = [
        'assistants',
    ];
}
