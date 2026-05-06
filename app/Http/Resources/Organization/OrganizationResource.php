<?php

declare(strict_types=1);

namespace App\Http\Resources\Organization;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class OrganizationResource extends JsonApiResource
{
    public $attributes = [
        'name',
        'created_at',
        'updated_at',
    ];
}
