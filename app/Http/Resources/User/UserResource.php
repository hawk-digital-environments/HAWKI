<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    public $attributes = [
        'name',
    ];
}
