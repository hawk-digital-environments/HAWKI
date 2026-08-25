<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Users;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class UserRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     *
     * Only the profile fields a user may edit about themselves are writable;
     * everything else on the schema is read-only.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:20'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
