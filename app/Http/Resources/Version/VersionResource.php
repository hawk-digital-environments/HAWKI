<?php

declare(strict_types=1);

namespace App\Http\Resources\Version;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class VersionResource extends JsonApiResource
{
    public $attributes = [
        'text',
        'version',
        'changed_keys',
        'created_at',
        'updated_at',
    ];
}
