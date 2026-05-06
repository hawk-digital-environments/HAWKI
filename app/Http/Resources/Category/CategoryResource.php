<?php

declare(strict_types=1);

namespace App\Http\Resources\Category;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class CategoryResource extends JsonApiResource
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
