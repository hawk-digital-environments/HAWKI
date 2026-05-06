<?php

declare(strict_types=1);

namespace App\Http\Resources\Tag;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class TagResource extends JsonApiResource
{
    public $attributes = [
        'text',
        'created_at',
        'updated_at',
    ];
}
