<?php

declare(strict_types=1);

namespace App\Http\Resources\AiTool;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AiToolResource extends JsonApiResource
{
    public $attributes = [
        'type',
        'name',
        'class_name',
        'description',
        'capability',
        'status',
        'active',
        'created_at',
        'updated_at',
    ];
}
